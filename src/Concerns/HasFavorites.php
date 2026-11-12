<?php

namespace Pharaonic\Laravel\Favorites\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Support\FavoriteManager;

/**
 * For a model that other models favorite, such as a post.
 */
trait HasFavorites
{
    /**
     * Delete the favorites the model received when it is deleted (not soft deleted).
     */
    public static function bootHasFavorites(): void
    {
        static::deleted(function (Model $model) {
            FavoriteManager::cleanUp($model, 'favoritable');
        });
    }

    /**
     * The favorites this model received.
     *
     * @return MorphMany<Favorite, $this>
     */
    public function favorites(): MorphMany
    {
        return FavoriteManager::relation($this, 'favoritable');
    }

    /**
     * Whether the favoriter favorites this model.
     */
    public function favoritedBy(Model $favoriter): bool
    {
        return FavoriteManager::has($favoriter, $this);
    }

    /**
     * Favorite this model as the favoriter. True when this call created the favorite.
     */
    public function favoriteBy(Model $favoriter): bool
    {
        return FavoriteManager::favorite($favoriter, $this);
    }

    /**
     * Remove the favoriter's favorite. True when this call removed it.
     */
    public function unfavoriteBy(Model $favoriter): bool
    {
        return FavoriteManager::unfavorite($favoriter, $this);
    }

    /**
     * Only the models the favoriter favorites.
     *
     * @param  Builder<static>  $query
     */
    public function scopeWhereFavoritedBy(Builder $query, Model $favoriter): void
    {
        $query->whereHas('favorites', function (Builder $favorites) use ($favoriter) {
            FavoriteManager::constrain($favorites, 'favoriter', $favoriter);
        });
    }
}
