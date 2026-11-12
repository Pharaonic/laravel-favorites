<?php

namespace Pharaonic\Laravel\Favorites\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\Favorites\Events\FavoriteCreated;
use Pharaonic\Laravel\Favorites\Events\FavoriteRemoved;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;
use Pharaonic\Laravel\Favorites\Tests\TestCase;

class EventsTest extends TestCase
{
    public function test_favorite_created_is_dispatched_once_per_inserted_favorite(): void
    {
        Event::fake([FavoriteCreated::class, FavoriteRemoved::class]);

        $user = User::create();
        $post = Post::create();
        $user->favorite($post);
        $user->favorite($post);
        $post->favoriteBy($user);

        Event::assertDispatchedTimes(FavoriteCreated::class, 1);
        Event::assertDispatched(FavoriteCreated::class, function (FavoriteCreated $event) use ($user, $post) {
            return $event->favoriter->is($user)
                && $event->favoritable->is($post)
                && $event->favorite->exists
                && $event->favorite->is(Favorite::query()->firstOrFail());
        });
        Event::assertNotDispatched(FavoriteRemoved::class);
    }

    public function test_favorite_removed_is_dispatched_once_per_deleted_favorite(): void
    {
        $user = User::create();
        $post = Post::create();
        $user->favorite($post);
        $id = Favorite::query()->firstOrFail()->getKey();

        Event::fake([FavoriteCreated::class, FavoriteRemoved::class]);

        $user->unfavorite($post);
        $user->unfavorite($post);
        $post->unfavoriteBy($user);

        Event::assertDispatchedTimes(FavoriteRemoved::class, 1);
        Event::assertDispatched(FavoriteRemoved::class, function (FavoriteRemoved $event) use ($user, $post, $id) {
            return $event->favoriter->is($user)
                && $event->favoritable->is($post)
                && $event->favorite->getKey() === $id
                && ! $event->favorite->exists;
        });
        Event::assertNotDispatched(FavoriteCreated::class);
    }

    public function test_toggle_favorite_dispatches_the_event_of_the_change_it_made(): void
    {
        Event::fake([FavoriteCreated::class, FavoriteRemoved::class]);

        $user = User::create();
        $post = Post::create();

        $user->toggleFavorite($post);
        Event::assertDispatchedTimes(FavoriteCreated::class, 1);
        Event::assertNotDispatched(FavoriteRemoved::class);

        $user->toggleFavorite($post);
        Event::assertDispatchedTimes(FavoriteCreated::class, 1);
        Event::assertDispatchedTimes(FavoriteRemoved::class, 1);
    }

    public function test_listeners_run_after_the_write(): void
    {
        $counts = [];

        Event::listen(FavoriteCreated::class, function () use (&$counts) {
            $counts[] = Favorite::query()->count();
        });
        Event::listen(FavoriteRemoved::class, function () use (&$counts) {
            $counts[] = Favorite::query()->count();
        });

        $user = User::create();
        $post = Post::create();
        $user->favorite($post);
        $user->unfavorite($post);

        $this->assertSame([1, 0], $counts);
    }

    public function test_cleanup_on_delete_dispatches_no_favorite_removed(): void
    {
        $post = Post::create();
        User::create()->favorite($post);

        Event::fake([FavoriteRemoved::class]);
        $post->delete();

        Event::assertNotDispatched(FavoriteRemoved::class);
        $this->assertSame(0, Favorite::query()->count());
    }
}
