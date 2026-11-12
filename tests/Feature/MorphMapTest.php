<?php

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Product;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;

test('without a morph map the class names are stored', function () {
    User::create()->favorite(Post::create());

    $favorite = Favorite::query()->firstOrFail();

    $this->assertSame(User::class, $favorite->favoriter_type);
    $this->assertSame(Post::class, $favorite->favoritable_type);
});

test('morph map aliases are stored and resolved', function () {
    Relation::morphMap(['user' => User::class, 'post' => Post::class]);

    $user = User::create();
    $post = Post::create();
    $user->favorite($post);

    $favorite = Favorite::query()->firstOrFail();

    $this->assertSame('user', $favorite->favoriter_type);
    $this->assertSame('post', $favorite->favoritable_type);
    $this->assertTrue($favorite->favoriter->is($user));
    $this->assertTrue($favorite->favoritable->is($post));
    $this->assertTrue($post->favoritedBy($user));
    $this->assertSame(1, Post::whereFavoritedBy($user)->count());
    $this->assertSame(1, (int) Post::withCount('favorites')->firstOrFail()->getAttribute('favorites_count'));
});

test('an enforced morph map refuses an unmapped model before writing', function () {
    if (! method_exists(Relation::class, 'enforceMorphMap')) {
        $this->markTestSkipped('Relation::enforceMorphMap() needs Laravel 8.');
    }

    Relation::enforceMorphMap(['user' => User::class, 'post' => Post::class]);

    $user = User::create();
    $this->assertTrue($user->favorite(Post::create()));

    try {
        $user->favorite(Product::create());
        $this->fail('An unmapped model was favorited.');
    } catch (ClassMorphViolationException $exception) {
        $this->assertStringContainsString(Product::class, $exception->getMessage());
    }

    $this->assertSame(1, Favorite::query()->count());
});
