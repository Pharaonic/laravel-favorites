<?php

namespace Pharaonic\Laravel\Favorites\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Pharaonic\Laravel\Favorites\Events\FavoriteCreated;
use Pharaonic\Laravel\Favorites\Events\FavoriteRemoved;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Relations\FavoritesMorphMany;
use Stringable;

/**
 * The one place that reads and writes favorites, shared by CanFavorite and HasFavorites.
 *
 * @internal
 */
final class FavoriteManager
{
    /**
     * The longest morph type the favorites table stores.
     */
    public const TYPE_LENGTH = 191;

    /**
     * The longest key the favorites table stores.
     */
    public const KEY_LENGTH = 64;

    /**
     * How many times a write runs when a concurrent request keeps getting in its way.
     */
    private const ATTEMPTS = 3;

    /**
     * Favorite the favoritable. True when this call created the favorite.
     */
    public static function favorite(Model $favoriter, Model $favoritable): bool
    {
        return self::insert($favoriter, $favoritable)[1];
    }

    /**
     * Remove the favorite. True when this call deleted it.
     */
    public static function unfavorite(Model $favoriter, Model $favoritable): bool
    {
        $favorite = self::findPair(self::pair($favoriter, $favoritable));

        return $favorite instanceof Favorite && self::remove($favorite, $favoriter, $favoritable);
    }

    /**
     * Favorite the favoritable, or remove the favorite when it exists. Returns the new state.
     *
     * The insert comes first, so the unique index rather than a read decides
     * which branch runs. Nothing serializes the insert and the delete, so two
     * concurrent toggles of one pair may both remove it, or one may remove the
     * favorite the other just created.
     */
    public static function toggle(Model $favoriter, Model $favoritable): bool
    {
        [$favorite, $created] = self::insert($favoriter, $favoritable);

        if ($created) {
            return true;
        }

        if ($favorite === null) {
            return self::has($favoriter, $favoritable);
        }

        // When another request deleted it first, the pair is not favorited either way.
        self::remove($favorite, $favoriter, $favoritable);

        return false;
    }

    /**
     * Whether the favoriter favorites the favoritable. False when either one is unsaved.
     */
    public static function has(Model $favoriter, Model $favoritable): bool
    {
        if (! self::isPersisted($favoriter) || ! self::isPersisted($favoritable)) {
            return false;
        }

        return Favorite::query()->where(self::pair($favoriter, $favoritable))->exists();
    }

    /**
     * The favorites given (favoriter) or received (favoritable) by the model.
     */
    public static function relation(Model $parent, string $side): MorphMany
    {
        $favorite = new Favorite;
        $table = $favorite->getTable();

        return new FavoritesMorphMany(
            $favorite->newQuery(),
            $parent,
            $table.'.'.$side.'_type',
            $table.'.'.$side.'_id',
            $parent->getKeyName()
        );
    }

    /**
     * Limit a favorites query to one favoriter or favoritable. An unsaved model matches nothing.
     */
    public static function constrain(Builder $query, string $side, Model $model): void
    {
        if (! self::isPersisted($model)) {
            $query->whereRaw('0 = 1');

            return;
        }

        [$type, $key] = self::endpoint($model, $side);

        $query->where($query->qualifyColumn($side.'_type'), $type);
        $query->where($query->qualifyColumn($side.'_id'), $key);
    }

    /**
     * Delete the favorites of a model that is being deleted, unless it is only soft deleted.
     */
    public static function cleanUp(Model $model, string $side): void
    {
        if (! (bool) Config::get('pharaonic.favorites.cleanup_on_delete', true)) {
            return;
        }

        if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
            return;
        }

        $key = self::key($model);

        if ($key === null) {
            return;
        }

        Favorite::query()->toBase()
            ->where($side.'_type', $model->getMorphClass())
            ->where($side.'_id', $key)
            ->delete();
    }

    /**
     * Whether the database refused a row because of a unique index.
     */
    public static function isUniqueViolation(QueryException $exception): bool
    {
        $errorInfo = is_array($exception->errorInfo) ? $exception->errorInfo : [];
        $sqlState = isset($errorInfo[0]) && is_string($errorInfo[0]) ? $errorInfo[0] : (string) $exception->getCode();
        $driverCode = $errorInfo[1] ?? null;

        // PostgreSQL: unique_violation.
        if ($sqlState === '23505') {
            return true;
        }

        // MySQL and MariaDB: ER_DUP_ENTRY, ER_DUP_ENTRY_WITH_KEY_NAME.
        // SQL Server: duplicate key in a unique index or constraint.
        if (in_array($driverCode, [1062, 1586, 2601, 2627], true)) {
            return true;
        }

        // SQLite reports every constraint failure as code 19.
        return str_contains($exception->getMessage(), 'UNIQUE constraint failed');
    }

    /**
     * Whether the database rolled the statement back to break a deadlock.
     */
    public static function isDeadlock(QueryException $exception): bool
    {
        $errorInfo = is_array($exception->errorInfo) ? $exception->errorInfo : [];
        $sqlState = isset($errorInfo[0]) && is_string($errorInfo[0]) ? $errorInfo[0] : (string) $exception->getCode();
        $driverCode = $errorInfo[1] ?? null;

        // MySQL and MariaDB report ER_LOCK_DEADLOCK (1213) as 40001, like SQL
        // Server's deadlock victim; PostgreSQL uses 40P01 for deadlock_detected.
        return in_array($sqlState, ['40001', '40P01'], true) || $driverCode === 1213;
    }

    /**
     * Insert the favorite, unless the pair already has one.
     *
     * @return array{0: Favorite|null, 1: bool} the favorite (null when a model event
     *                                          cancelled the insert), and whether this call created it
     */
    private static function insert(Model $favoriter, Model $favoritable): array
    {
        $pair = self::pair($favoriter, $favoritable);

        for ($attempt = 1; ; $attempt++) {
            $favorite = new Favorite($pair);
            $connection = $favorite->getConnection();

            try {
                // Inside a transaction, a savepoint keeps a refused insert from
                // aborting the caller's transaction (PostgreSQL aborts it otherwise).
                $saved = $connection->transactionLevel() > 0
                    ? $connection->transaction(function () use ($favorite) {
                        return $favorite->save();
                    })
                    : $favorite->save();
            } catch (QueryException $exception) {
                if (self::isUniqueViolation($exception)) {
                    $existing = self::findPair($pair, true);

                    if ($existing instanceof Favorite) {
                        return [$existing, false];
                    }

                    // The favorite was deleted between the insert and the read: insert
                    // again. When it keeps missing, another unique index refused the
                    // row, which is not ours to swallow.
                } elseif (! self::canRetry($exception, $connection)) {
                    throw $exception;
                }

                if ($attempt >= self::ATTEMPTS) {
                    throw $exception;
                }

                continue;
            }

            if (! $saved) {
                return [null, false];
            }

            Event::dispatch(new FavoriteCreated($favorite, $favoriter, $favoritable));

            return [$favorite, true];
        }
    }

    /**
     * Delete the favorite. True when this call deleted the row.
     */
    private static function remove(Favorite $favorite, Model $favoriter, Model $favoritable): bool
    {
        $deleted = false;

        for ($attempt = 1; ; $attempt++) {
            try {
                $deleted = Favorite::query()->toBase()->where($favorite->getKeyName(), $favorite->getKey())->delete() > 0;

                break;
            } catch (QueryException $exception) {
                if ($attempt >= self::ATTEMPTS || ! self::canRetry($exception, $favorite->getConnection())) {
                    throw $exception;
                }
            }
        }

        if ($deleted) {
            $favorite->exists = false;

            Event::dispatch(new FavoriteRemoved($favorite, $favoriter, $favoritable));
        }

        return $deleted;
    }

    /**
     * The pair's favorite, read from the write connection so a lagging replica
     * can't hide a row the statement before just wrote or kept.
     *
     * With $refused, the unique index just refused the pair's insert. Inside a
     * transaction, a plain read on MySQL (REPEATABLE READ) returns the snapshot
     * the transaction started from, which misses a row another request committed
     * since; a locking read returns it. The refused insert already holds that lock.
     *
     * @param  array{favoriter_type: string, favoriter_id: string, favoritable_type: string, favoritable_id: string}  $pair
     */
    private static function findPair(array $pair, bool $refused = false): ?Favorite
    {
        $query = Favorite::query()->where($pair);
        $query->getQuery()->useWritePdo();

        if ($refused && $query->getQuery()->getConnection()->transactionLevel() > 0) {
            $query->sharedLock();
        }

        $favorite = $query->first();

        return $favorite instanceof Favorite ? $favorite : null;
    }

    /**
     * Whether a failed statement can simply run again: a deadlock outside a
     * transaction. Inside one, the database rolled the whole transaction back,
     * so only the caller can retry it.
     */
    private static function canRetry(QueryException $exception, ConnectionInterface $connection): bool
    {
        return self::isDeadlock($exception) && $connection->transactionLevel() === 0;
    }

    /**
     * The four columns that identify the favorite of a pair.
     *
     * @return array{favoriter_type: string, favoriter_id: string, favoritable_type: string, favoritable_id: string}
     */
    private static function pair(Model $favoriter, Model $favoritable): array
    {
        [$favoriterType, $favoriterId] = self::endpoint($favoriter, 'favoriter');
        [$favoritableType, $favoritableId] = self::endpoint($favoritable, 'favoritable');

        return [
            'favoriter_type' => $favoriterType,
            'favoriter_id' => $favoriterId,
            'favoritable_type' => $favoritableType,
            'favoritable_id' => $favoritableId,
        ];
    }

    /**
     * The morph type and key the favorites table stores for the model.
     *
     * @return array{0: string, 1: string}
     *
     * @throws InvalidArgumentException when the model is unsaved, or its type or key does not fit
     */
    private static function endpoint(Model $model, string $side): array
    {
        if (! $model->exists) {
            throw new InvalidArgumentException(sprintf(
                'The %s [%s] is not saved: save it before it favorites or is favorited.', $side, get_class($model)
            ));
        }

        $key = self::key($model);

        if ($key === null) {
            throw new InvalidArgumentException(sprintf(
                'The %s [%s] has no usable primary key.', $side, get_class($model)
            ));
        }

        if (mb_strlen($key) > self::KEY_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The key of the %s [%s] is %d characters long; the favorites table stores keys of up to %d characters.',
                $side, get_class($model), mb_strlen($key), self::KEY_LENGTH
            ));
        }

        $type = $model->getMorphClass();

        if (mb_strlen($type) > self::TYPE_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'The morph type of the %s [%s] is %d characters long; the favorites table stores types of up to %d characters. '
                .'Register a shorter alias with Relation::morphMap().',
                $side, get_class($model), mb_strlen($type), self::TYPE_LENGTH
            ));
        }

        return [$type, $key];
    }

    /**
     * Whether the model is saved and has a usable key.
     */
    private static function isPersisted(Model $model): bool
    {
        return $model->exists && self::key($model) !== null;
    }

    /**
     * The model key as the string the favorites table stores, or null when it has none.
     */
    private static function key(Model $model): ?string
    {
        $key = $model->getKey();

        if (! is_int($key) && ! is_string($key) && ! $key instanceof Stringable) {
            return null;
        }

        $key = (string) $key;

        return $key === '' ? null : $key;
    }
}
