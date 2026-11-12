<?php

namespace Pharaonic\Laravel\Favorites\Tests\Feature;

use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Product;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\SoftDeletableMember;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\SoftDeletablePost;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;
use Pharaonic\Laravel\Favorites\Tests\TestCase;

class DeletionTest extends TestCase
{
    public function test_deleting_a_favoritable_deletes_the_favorites_it_received(): void
    {
        $user = User::create();
        [$deleted, $kept] = [Post::create(), Post::create()];
        $user->favorite($deleted);
        Bot::create()->favorite($deleted);
        $user->favorite($kept);

        $deleted->delete();

        $this->assertSame(0, Favorite::whereFavoritable($deleted)->count());
        $this->assertTrue($user->hasFavorited($kept));
        $this->assertSame(1, Favorite::query()->count());
    }

    public function test_deleting_a_favoriter_deletes_the_favorites_it_gave(): void
    {
        $bot = Bot::create();
        $other = Bot::create();
        $post = Post::create();
        $bot->favorite($post);
        $bot->favorite(Product::create());
        $other->favorite($post);

        $bot->delete();

        $this->assertSame(0, Favorite::whereFavoriter($bot)->count());
        $this->assertTrue($post->favoritedBy($other));
    }

    public function test_deleting_a_model_with_both_traits_deletes_both_directions(): void
    {
        $alice = User::create();
        $bob = User::create();
        $post = Post::create();

        $alice->favorite($bob);
        $bob->favorite($alice);
        $alice->favorite($alice);
        $alice->favorite($post);
        $bob->favorite($post);

        $alice->delete();

        $this->assertSame(1, Favorite::query()->count());
        $this->assertTrue($post->favoritedBy($bob));
    }

    public function test_a_soft_deleted_favoritable_keeps_its_favorites_until_force_deleted(): void
    {
        $user = User::create();
        $post = SoftDeletablePost::create();
        $user->favorite($post);

        $post->delete();
        $this->assertSame(1, Favorite::query()->count());

        $post->restore();
        $this->assertTrue($post->favoritedBy($user));

        $post->delete();
        $post->forceDelete();
        $this->assertSame(0, Favorite::query()->count());
    }

    public function test_force_deleting_a_favoritable_that_was_never_soft_deleted_deletes_its_favorites(): void
    {
        $post = SoftDeletablePost::create();
        User::create()->favorite($post);

        $post->forceDelete();

        $this->assertSame(0, Favorite::query()->count());
    }

    public function test_a_soft_deleted_favoriter_keeps_its_favorites_until_force_deleted(): void
    {
        $member = SoftDeletableMember::create();
        $post = Post::create();
        $member->favorite($post);

        $member->delete();
        $this->assertSame(1, Favorite::query()->count());

        $member->restore();
        $this->assertTrue($member->hasFavorited($post));

        $member->forceDelete();
        $this->assertSame(0, Favorite::query()->count());
    }

    public function test_cleanup_can_be_turned_off(): void
    {
        $this->app['config']->set('pharaonic.favorites.cleanup_on_delete', false);

        $user = User::create();
        $post = Post::create();
        $user->favorite($post);
        $post->delete();
        $user->delete();

        $this->assertSame(1, Favorite::query()->count());
    }

    public function test_query_builder_deletes_skip_model_events_and_cleanup(): void
    {
        $post = Post::create();
        User::create()->favorite($post);

        Post::query()->whereKey($post->getKey())->delete();

        $this->assertSame(1, Favorite::query()->count());

        // The documented manual cleanup.
        Favorite::query()->where('favoritable_type', $post->getMorphClass())
            ->whereNotIn('favoritable_id', Post::query()->pluck('id')->map(function ($value) {
                return (string) $value;
            }))
            ->delete();

        $this->assertSame(0, Favorite::query()->count());
    }
}
