<?php

namespace Pharaonic\Laravel\Favorites\Tests\Feature;

use Illuminate\Database\ClassMorphViolationException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Product;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;
use Pharaonic\Laravel\Favorites\Tests\TestCase;

class MorphMapTest extends TestCase
{
    public function test_without_a_morph_map_the_class_names_are_stored(): void
    {
        User::create()->favorite(Post::create());

        $favorite = Favorite::query()->firstOrFail();

        $this->assertSame(User::class, $favorite->favoriter_type);
        $this->assertSame(Post::class, $favorite->favoritable_type);
    }

    public function test_morph_map_aliases_are_stored_and_resolved(): void
    {
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
    }

    public function test_an_enforced_morph_map_refuses_an_unmapped_model_before_writing(): void
    {
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
    }
}
