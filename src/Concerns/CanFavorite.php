<?php

namespace Pharaonic\Laravel\Favorites\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Pharaonic\Laravel\Favorites\Support\FavoriteManager;

/**
 * For a model that favorites other models, such as a user.
 */
trait CanFavorite
{
    /**
     * Delete the favorites the model gave when it is deleted (not soft deleted).
     */
    public static function bootCanFavorite(): void
    {
        static::deleted(function (Model $model) {
            FavoriteManager::cleanUp($model, 'favoriter');
        });
    }

    /**
     * The favorites this model gave.
     */
    public function givenFavorites(): MorphMany
    {
        return FavoriteManager::relation($this, 'favoriter');
    }

    /**
     * Favorite the model. True when this call created the favorite, false when it already existed.
     */
    public function favorite(Model $favoritable): bool
    {
        return FavoriteManager::favorite($this, $favoritable);
    }

    /**
     * Remove the favorite. True when this call removed it, false when there was none.
     */
    public function unfavorite(Model $favoritable): bool
    {
        return FavoriteManager::unfavorite($this, $favoritable);
    }

    /**
     * Whether this model favorites the model.
     */
    public function hasFavorited(Model $favoritable): bool
    {
        return FavoriteManager::has($this, $favoritable);
    }

    /**
     * Favorite the model, or remove the favorite. Returns the new state: true when favorited.
     */
    public function toggleFavorite(Model $favoritable): bool
    {
        return FavoriteManager::toggle($this, $favoritable);
    }
}
