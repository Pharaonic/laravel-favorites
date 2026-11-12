<?php

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Favorites\FavoritesServiceProvider;

test('the provider is registered', function () {
    $this->assertNotEmpty($this->app->getProviders(FavoritesServiceProvider::class));
});

test('the configuration defaults are merged', function () {
    $this->assertSame('favorites', $this->app['config']->get('pharaonic.favorites.table'));
    $this->assertTrue($this->app['config']->get('pharaonic.favorites.cleanup_on_delete'));
});

test('the configuration is published to config pharaonic', function () {
    $source = realpath(__DIR__.'/../../config/favorites.php');

    foreach (['pharaonic', 'laravel-favorites', 'pharaonic-config', 'favorites-config'] as $tag) {
        $paths = ServiceProvider::pathsToPublish(FavoritesServiceProvider::class, $tag);
        $published = [];

        foreach ($paths as $from => $to) {
            $published[realpath($from) ?: $from] = $to;
        }

        $this->assertArrayHasKey($source, $published, $tag);
        $this->assertSame($this->app->configPath('pharaonic/favorites.php'), $published[$source]);
    }
});

test('the migrations are published to database migrations', function () {
    $source = realpath(__DIR__.'/../../database/migrations');

    foreach (['pharaonic', 'laravel-favorites', 'pharaonic-migrations', 'favorites-migrations'] as $tag) {
        $published = [];

        foreach (ServiceProvider::pathsToPublish(FavoritesServiceProvider::class, $tag) as $from => $to) {
            $published[realpath($from) ?: $from] = $to;
        }

        $this->assertArrayHasKey($source, $published, $tag);
        $this->assertSame($this->app->databasePath('migrations'), $published[$source]);
    }
});
