<?php

namespace Pharaonic\Laravel\Favorites\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Product;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\UuidModel;
use Pharaonic\Laravel\Favorites\Tests\TestCase;

class FavoritingTest extends TestCase
{
    public function test_favorite_returns_true_only_when_it_creates_the_favorite(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($user->favorite($post));
        $this->assertFalse($user->favorite($post));
        $this->assertFalse($user->favorite($post));
        $this->assertSame(1, Favorite::query()->count());
    }

    public function test_a_repeated_favorite_leaves_the_row_and_its_timestamps_untouched(): void
    {
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
    }

    public function test_unfavorite_returns_true_only_when_it_removes_the_favorite(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertFalse($user->unfavorite($post));

        $user->favorite($post);

        $this->assertTrue($user->unfavorite($post));
        $this->assertFalse($user->unfavorite($post));
        $this->assertSame(0, Favorite::query()->count());
    }

    public function test_has_favorited_and_favorited_by_report_the_current_state(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertFalse($user->hasFavorited($post));
        $this->assertFalse($post->favoritedBy($user));

        $user->favorite($post);

        $this->assertTrue($user->hasFavorited($post));
        $this->assertTrue($post->favoritedBy($user));
    }

    public function test_favorite_by_and_unfavorite_by_return_whether_they_changed_anything(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($post->favoriteBy($user));
        $this->assertFalse($post->favoriteBy($user));
        $this->assertTrue($post->unfavoriteBy($user));
        $this->assertFalse($post->unfavoriteBy($user));
    }

    public function test_both_sides_work_on_the_same_row(): void
    {
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
    }

    public function test_toggle_favorite_returns_the_new_state(): void
    {
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($user->toggleFavorite($post));
        $this->assertSame(1, Favorite::query()->count());

        $this->assertFalse($user->toggleFavorite($post));
        $this->assertSame(0, Favorite::query()->count());

        $this->assertTrue($user->toggleFavorite($post));
        $this->assertTrue($user->hasFavorited($post));
        $this->assertSame(1, Favorite::query()->count());
    }

    public function test_a_favoriter_can_favorite_any_model(): void
    {
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
    }

    public function test_a_post_and_a_comment_with_the_same_id_are_different_targets(): void
    {
        $user = User::create();
        $post = Post::create();
        $comment = Comment::create();
        $this->assertSame($post->getKey(), $comment->getKey());

        $user->favorite($post);

        $this->assertTrue($user->hasFavorited($post));
        $this->assertFalse($user->hasFavorited($comment));
        $this->assertTrue($user->favorite($comment));
    }

    public function test_many_favoriters_can_favorite_many_targets(): void
    {
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
    }

    public function test_the_favoriter_does_not_have_to_be_a_user_or_signed_in(): void
    {
        $bot = Bot::create();
        $post = Post::create();

        $this->assertFalse(Auth::check());
        $this->assertTrue($bot->favorite($post));
        $this->assertTrue($post->favoritedBy($bot));
        $this->assertInstanceOf(Bot::class, $post->favorites()->firstOrFail()->favoriter);
    }

    public function test_writes_reject_an_unsaved_favoriter_without_creating_a_row(): void
    {
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
    }

    public function test_writes_reject_an_unsaved_favoritable_without_creating_a_row(): void
    {
        $user = User::create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The favoritable ['.Post::class.'] is not saved');

        try {
            $user->favorite(new Post);
        } finally {
            $this->assertSame(0, Favorite::query()->count());
        }
    }

    public function test_reads_treat_an_unsaved_model_as_not_favorited(): void
    {
        $user = User::create();
        $post = Post::create();
        $user->favorite($post);

        $this->assertFalse($user->hasFavorited(new Post));
        $this->assertFalse($post->favoritedBy(new User));
        $this->assertSame(0, Post::whereFavoritedBy(new User)->count());
    }

    public function test_a_key_longer_than_the_column_is_rejected(): void
    {
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
    }

    public function test_a_morph_type_longer_than_the_column_is_rejected(): void
    {
        Relation::morphMap([str_repeat('p', 192) => Post::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Register a shorter alias with Relation::morphMap()');

        User::create()->favorite(Post::create());
    }
}
