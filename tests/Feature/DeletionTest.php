<?php

use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Product;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\SoftDeletableMember;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\SoftDeletablePost;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;

test('deleting a favoritable deletes the favorites it received', function () {
    $user = User::create();
    [$deleted, $kept] = [Post::create(), Post::create()];
    $user->favorite($deleted);
    Bot::create()->favorite($deleted);
    $user->favorite($kept);

    $deleted->delete();

    $this->assertSame(0, Favorite::whereFavoritable($deleted)->count());
    $this->assertTrue($user->hasFavorited($kept));
    $this->assertSame(1, Favorite::query()->count());
});

test('deleting a favoriter deletes the favorites it gave', function () {
    $bot = Bot::create();
    $other = Bot::create();
    $post = Post::create();
    $bot->favorite($post);
    $bot->favorite(Product::create());
    $other->favorite($post);

    $bot->delete();

    $this->assertSame(0, Favorite::whereFavoriter($bot)->count());
    $this->assertTrue($post->favoritedBy($other));
});

test('deleting a model with both traits deletes both directions', function () {
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
});

test('a soft deleted favoritable keeps its favorites until force deleted', function () {
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
});

test('force deleting a favoritable that was never soft deleted deletes its favorites', function () {
    $post = SoftDeletablePost::create();
    User::create()->favorite($post);

    $post->forceDelete();

    $this->assertSame(0, Favorite::query()->count());
});

test('a soft deleted favoriter keeps its favorites until force deleted', function () {
    $member = SoftDeletableMember::create();
    $post = Post::create();
    $member->favorite($post);

    $member->delete();
    $this->assertSame(1, Favorite::query()->count());

    $member->restore();
    $this->assertTrue($member->hasFavorited($post));

    $member->forceDelete();
    $this->assertSame(0, Favorite::query()->count());
});

test('cleanup can be turned off', function () {
    $this->app['config']->set('pharaonic.favorites.cleanup_on_delete', false);

    $user = User::create();
    $post = Post::create();
    $user->favorite($post);
    $post->delete();
    $user->delete();

    $this->assertSame(1, Favorite::query()->count());
});

test('query builder deletes skip model events and cleanup', function () {
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
});
