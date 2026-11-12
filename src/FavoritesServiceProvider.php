<?php

namespace Pharaonic\Laravel\Favorites;

use Illuminate\Support\ServiceProvider;

final class FavoritesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/favorites.php', 'pharaonic.favorites');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/favorites.php' => $this->app->configPath('pharaonic/favorites.php'),
            ], ['pharaonic', 'laravel-favorites', 'pharaonic-config', 'favorites-config']);

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], ['pharaonic', 'laravel-favorites', 'pharaonic-migrations', 'favorites-migrations']);
        }
    }
}
