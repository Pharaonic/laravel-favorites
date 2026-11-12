<?php

namespace Pharaonic\Laravel\Favorites\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\Favorites\FavoritesServiceProvider;
use Pharaonic\Laravel\Favorites\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_the_provider_is_registered(): void
    {
        $this->assertNotEmpty($this->app->getProviders(FavoritesServiceProvider::class));
    }

    public function test_the_configuration_defaults_are_merged(): void
    {
        $this->assertSame('favorites', $this->app['config']->get('pharaonic.favorites.table'));
        $this->assertTrue($this->app['config']->get('pharaonic.favorites.cleanup_on_delete'));
    }

    public function test_the_configuration_is_published_to_config_pharaonic(): void
    {
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
    }

    public function test_the_migrations_are_published_to_database_migrations(): void
    {
        $source = realpath(__DIR__.'/../../database/migrations');

        foreach (['pharaonic', 'laravel-favorites', 'pharaonic-migrations', 'favorites-migrations'] as $tag) {
            $published = [];

            foreach (ServiceProvider::pathsToPublish(FavoritesServiceProvider::class, $tag) as $from => $to) {
                $published[realpath($from) ?: $from] = $to;
            }

            $this->assertArrayHasKey($source, $published, $tag);
            $this->assertSame($this->app->databasePath('migrations'), $published[$source]);
        }
    }
}
