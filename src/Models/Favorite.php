<?php

namespace Pharaonic\Laravel\Favorites\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;
use Pharaonic\Laravel\Favorites\Support\FavoriteManager;

/**
 * A favorite given by a favoriter model to a favoritable model.
 *
 * @property int $id
 * @property string $favoriter_type
 * @property string $favoriter_id
 * @property string $favoritable_type
 * @property string $favoritable_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class Favorite extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'favoriter_type',
        'favoriter_id',
        'favoritable_type',
        'favoritable_id',
    ];

    /**
     * The table from the pharaonic.favorites.table setting, unless a subclass sets $table.
     *
     * @return string
     */
    public function getTable()
    {
        if ($this->table) {
            return $this->table;
        }

        $table = Config::get('pharaonic.favorites.table', 'favorites');

        return is_string($table) && $table !== '' ? $table : 'favorites';
    }

    /**
     * The model that gave the favorite.
     *
     * @return MorphTo<Model, $this>
     */
    public function favoriter(): MorphTo
    {
        return $this->morphTo('favoriter', 'favoriter_type', 'favoriter_id');
    }

    /**
     * The model that received the favorite.
     *
     * @return MorphTo<Model, $this>
     */
    public function favoritable(): MorphTo
    {
        return $this->morphTo('favoritable', 'favoritable_type', 'favoritable_id');
    }

    /**
     * Only the favorites given by the favoriter.
     *
     * @param  Builder<Favorite>  $query
     */
    public function scopeWhereFavoriter(Builder $query, Model $favoriter): void
    {
        FavoriteManager::constrain($query, 'favoriter', $favoriter);
    }

    /**
     * Only the favorites received by the favoritable.
     *
     * @param  Builder<Favorite>  $query
     */
    public function scopeWhereFavoritable(Builder $query, Model $favoritable): void
    {
        FavoriteManager::constrain($query, 'favoritable', $favoritable);
    }
}
