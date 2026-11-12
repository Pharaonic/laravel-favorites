<?php

namespace Pharaonic\Laravel\Favorites\Relations;

use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Grammar;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Support\FavoriteManager;

/**
 * The favorites relation of a favoriter or favoritable model.
 *
 * The favorites table stores ids as strings, so integer, UUID and ULID keys can
 * share it. This relation compares the parent key as a string too: PostgreSQL
 * refuses to compare a bigint or uuid column with a varchar one, and on MySQL
 * and SQLite the string comparison is what lets the unique index be used.
 *
 * @internal
 *
 * @template TDeclaringModel of Model
 *
 * @extends MorphMany<Favorite, TDeclaringModel>
 */
final class FavoritesMorphMany extends MorphMany
{
    /**
     * Declared here so PHPStan infers TDeclaringModel from `new FavoritesMorphMany(...)`:
     * PHPStan 2.1.0 does not read it from the inherited constructor.
     *
     * @param  Builder<Favorite>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     */
    public function __construct(Builder $query, Model $parent, $type, $id, $localKey)
    {
        parent::__construct($query, $parent, $type, $id, $localKey);
    }

    /**
     * The parent key, as the string the favorites table stores.
     *
     * @return mixed
     */
    public function getParentKey()
    {
        $key = parent::getParentKey();

        return FavoriteManager::keyToString($key) ?? $key;
    }

    /**
     * Constrain eager loading to the parents' keys, as strings.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $keys = [];

        foreach ($this->getKeys($models, $this->localKey) as $key) {
            $key = FavoriteManager::keyToString($key);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        $this->query->whereIn($this->foreignKey, $keys);
        $this->query->where($this->morphType, $this->morphClass);
    }

    /**
     * The subquery behind has(), whereHas() and withCount().
     *
     * @param  Builder<Favorite>  $query
     * @param  Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return Builder<Favorite>
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $connection = $query->getQuery()->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : null;

        $template = match ($driver) {
            // CONCAT() takes the collation of the favorites column, so a connection
            // collation that differs from the table's cannot make it fail.
            'mysql', 'mariadb' => 'concat(%s)',
            'pgsql' => 'cast(%s as varchar)',
            'sqlite' => 'cast(%s as text)',
            default => null,
        };

        if ($template === null || $query->getQuery()->from == $parentQuery->getQuery()->from) {
            return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
        }

        // The grammar wraps the column when the query is compiled.
        $parentKeyAsString = new class($template, $this->getQualifiedParentKeyName()) implements ExpressionContract
        {
            public function __construct(private string $template, private string $column) {}

            public function getValue(Grammar $grammar): string
            {
                return sprintf($this->template, $grammar->wrap($this->column));
            }
        };

        $query->select($columns);
        $query->whereColumn($parentKeyAsString, '=', $this->getExistenceCompareKey());
        $query->where($query->qualifyColumn($this->getMorphType()), $this->morphClass);

        return $query;
    }
}
