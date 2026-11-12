<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Product;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\UuidModel;

test('favorite returns true only when it creates the favorite', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($user->favorite($post));
    $this->assertFalse($user->favorite($post));
    $this->assertFalse($user->favorite($post));
    $this->assertSame(1, Favorite::query()->count());
});

test('a repeated favorite leaves the row and its timestamps untouched', function () {
    $user = User::create();
    $post = Post::create();

    try {
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));
        $this->assertTrue($user->favorite($post));

        Carbon::setTestNow(Carbon::parse('2026-01-02 10:00:00'));
        $this->assertFalse($user->favorite($post));
        $this->assertFalse($post->favoriteBy($user));
    } finally {
        Carbon::setTestNow();
    }

    $favorite = Favorite::query()->firstOrFail();
    $this->assertSame(1, Favorite::query()->count());
    $this->assertSame('2026-01-01 10:00:00', (string) $favorite->created_at);
    $this->assertSame('2026-01-01 10:00:00', (string) $favorite->updated_at);
});

test('unfavorite returns true only when it removes the favorite', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertFalse($user->unfavorite($post));

    $user->favorite($post);

    $this->assertTrue($user->unfavorite($post));
    $this->assertFalse($user->unfavorite($post));
    $this->assertSame(0, Favorite::query()->count());
});

test('has favorited and favorited by report the current state', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertFalse($user->hasFavorited($post));
    $this->assertFalse($post->favoritedBy($user));

    $user->favorite($post);

    $this->assertTrue($user->hasFavorited($post));
    $this->assertTrue($post->favoritedBy($user));
});

test('favorite by and unfavorite by return whether they changed anything', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($post->favoriteBy($user));
    $this->assertFalse($post->favoriteBy($user));
    $this->assertTrue($post->unfavoriteBy($user));
    $this->assertFalse($post->unfavoriteBy($user));
});

test('both sides work on the same row', function () {
    $user = User::create();
    $post = Post::create();

    $user->favorite($post);
    $this->assertTrue($post->favoritedBy($user));
    $this->assertFalse($post->favoriteBy($user));

    $this->assertTrue($post->unfavoriteBy($user));
    $this->assertFalse($user->hasFavorited($post));

    $this->assertTrue($post->favoriteBy($user));
    $this->assertTrue($user->hasFavorited($post));
    $this->assertTrue($user->unfavorite($post));
    $this->assertSame(0, Favorite::query()->count());
});

test('toggle favorite returns the new state', function () {
    $user = User::create();
    $post = Post::create();

    $this->assertTrue($user->toggleFavorite($post));
    $this->assertSame(1, Favorite::query()->count());

    $this->assertFalse($user->toggleFavorite($post));
    $this->assertSame(0, Favorite::query()->count());

    $this->assertTrue($user->toggleFavorite($post));
    $this->assertTrue($user->hasFavorited($post));
    $this->assertSame(1, Favorite::query()->count());
});

test('a favoriter can favorite any model', function () {
    $user = User::create();
    $other = User::create();

    $this->assertTrue($user->favorite(Post::create()));
    $this->assertTrue($user->favorite(Comment::create()));
    $this->assertTrue($user->favorite(Product::create()));
    $this->assertTrue($user->favorite($other));
    $this->assertTrue($user->favorite($user));

    $this->assertSame(5, $user->givenFavorites()->count());
    $this->assertTrue($other->favoritedBy($user));
    $this->assertTrue($user->favoritedBy($user));
});

test('a post and a comment with the same id are different targets', function () {
    $user = User::create();
    $post = Post::create();
    $comment = Comment::create();
    $this->assertSame($post->getKey(), $comment->getKey());

    $user->favorite($post);

    $this->assertTrue($user->hasFavorited($post));
    $this->assertFalse($user->hasFavorited($comment));
    $this->assertTrue($user->favorite($comment));
});

test('many favoriters can favorite many targets', function () {
    $alice = User::create();
    $bob = User::create();
    $first = Post::create();
    $second = Post::create();

    $this->assertTrue($alice->favorite($first));
    $this->assertTrue($bob->favorite($first));
    $this->assertTrue($alice->favorite($second));

    $this->assertSame(2, $first->favorites()->count());
    $this->assertSame(1, $second->favorites()->count());
    $this->assertSame(2, $alice->givenFavorites()->count());
    $this->assertSame(1, $bob->givenFavorites()->count());
});

test('the favoriter does not have to be a user or signed in', function () {
    $bot = Bot::create();
    $post = Post::create();

    $this->assertFalse(Auth::check());
    $this->assertTrue($bot->favorite($post));
    $this->assertTrue($post->favoritedBy($bot));
    $this->assertInstanceOf(Bot::class, $post->favorites()->firstOrFail()->favoriter);
});

test('writes reject an unsaved favoriter without creating a row', function () {
    $post = Post::create();

    foreach (['favorite', 'unfavorite', 'toggleFavorite'] as $method) {
        try {
            (new User)->{$method}($post);
            $this->fail("{$method}() accepted an unsaved favoriter.");
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('The favoriter ['.User::class.'] is not saved', $exception->getMessage());
        }
    }

    $this->expectException(InvalidArgumentException::class);

    try {
        $post->favoriteBy(new User);
    } finally {
        $this->assertSame(0, Favorite::query()->count());
    }
});

test('writes reject an unsaved favoritable without creating a row', function () {
    $user = User::create();

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('The favoritable ['.Post::class.'] is not saved');

    try {
        $user->favorite(new Post);
    } finally {
        $this->assertSame(0, Favorite::query()->count());
    }
});

test('reads treat an unsaved model as not favorited', function () {
    $user = User::create();
    $post = Post::create();
    $user->favorite($post);

    $this->assertFalse($user->hasFavorited(new Post));
    $this->assertFalse($post->favoritedBy(new User));
    $this->assertSame(0, Post::whereFavoritedBy(new User)->count());
});

test('a key longer than the column is rejected', function () {
    $target = new UuidModel;
    $target->exists = true;
    $target->id = str_repeat('a', 65);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('is 65 characters long; the favorites table stores keys of up to 64 characters');

    try {
        User::create()->favorite($target);
    } finally {
        $this->assertSame(0, Favorite::query()->count());
    }
});

test('a morph type longer than the column is rejected', function () {
    Relation::morphMap([str_repeat('p', 192) => Post::class]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Register a shorter alias with Relation::morphMap()');

    User::create()->favorite(Post::create());
});
