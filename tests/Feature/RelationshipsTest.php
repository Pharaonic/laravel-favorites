<?php

namespace Pharaonic\Laravel\Favorites\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Bot;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Comment;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;
use Pharaonic\Laravel\Favorites\Tests\TestCase;

class RelationshipsTest extends TestCase
{
    public function test_given_favorites_and_favorites_are_morph_many_relations(): void
    {
        $user = User::create();

        $this->assertInstanceOf(MorphMany::class, $user->givenFavorites());
        $this->assertSame('favoriter_type', $user->givenFavorites()->getMorphType());
        $this->assertSame('favorites.favoriter_id', $user->givenFavorites()->getQualifiedForeignKeyName());

        $this->assertInstanceOf(MorphMany::class, $user->favorites());
        $this->assertSame('favoritable_type', $user->favorites()->getMorphType());
        $this->assertSame('favorites.favoritable_id', $user->favorites()->getQualifiedForeignKeyName());
    }

    public function test_a_model_with_both_traits_keeps_given_and_received_favorites_apart(): void
    {
        $alice = User::create();
        $bob = User::create();

        $alice->favorite($bob);

        $this->assertSame(1, $alice->givenFavorites()->count());
        $this->assertSame(0, $alice->favorites()->count());
        $this->assertSame(0, $bob->givenFavorites()->count());
        $this->assertSame(1, $bob->favorites()->count());
        $this->assertTrue($bob->favoritedBy($alice));
        $this->assertFalse($alice->favoritedBy($bob));
    }

    public function test_the_favorite_resolves_both_ends_through_morph_to(): void
    {
        $bot = Bot::create();
        $comment = Comment::create();
        $bot->favorite($comment);

        $favorite = Favorite::query()->firstOrFail();

        $this->assertInstanceOf(MorphTo::class, $favorite->favoriter());
        $this->assertInstanceOf(MorphTo::class, $favorite->favoritable());
        $this->assertTrue($favorite->favoriter->is($bot));
        $this->assertTrue($favorite->favoritable->is($comment));
    }

    public function test_favorites_count_runs_a_count_query(): void
    {
        $post = Post::create();
        User::create()->favorite($post);
        User::create()->favorite($post);

        DB::enableQueryLog();
        $count = $post->favorites()->count();
        $queries = DB::getQueryLog();

        $this->assertSame(2, $count);
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('count(*)', $queries[0]['query']);
        $this->assertFalse($post->relationLoaded('favorites'));
    }

    public function test_with_count_and_load_count_count_the_favorites(): void
    {
        [$first, $second] = [Post::create(), Post::create()];
        User::create()->favorite($first);
        User::create()->favorite($first);
        User::create()->favorite($second);
        Post::create();

        $counts = Post::withCount('favorites')->orderBy('id')->get()->pluck('favorites_count')->map(function ($value) {
            return (int) $value;
        })->all();
        $this->assertSame([2, 1, 0], $counts);

        $second->loadCount('favorites');
        $this->assertSame(1, (int) $second->getAttribute('favorites_count'));
    }

    public function test_eager_loading_favorites_and_favoriters_takes_a_fixed_number_of_queries(): void
    {
        foreach (range(1, 3) as $i) {
            $post = Post::create();
            User::create()->favorite($post);
            Bot::create()->favorite($post);
        }

        DB::enableQueryLog();
        $posts = Post::with('favorites.favoriter')->get();
        $names = $posts->flatMap(function (Post $post) {
            return $post->favorites->map(function (Favorite $favorite) {
                return get_class($favorite->favoriter);
            });
        });

        // posts, favorites, users, bots.
        $this->assertCount(4, DB::getQueryLog());
        $this->assertCount(6, $names);
    }

    public function test_given_favorites_eager_load_their_favoritables(): void
    {
        $user = User::create();
        $user->favorite(Post::create());
        $user->favorite(Comment::create());

        $favorites = $user->givenFavorites()->with('favoritable')->latest()->get();

        $this->assertCount(2, $favorites);
        $this->assertEqualsCanonicalizing(
            [Post::class, Comment::class],
            $favorites->map(function (Favorite $favorite) {
                return get_class($favorite->favoritable);
            })->all()
        );
    }

    public function test_where_favorited_by_keeps_only_the_models_the_favoriter_favorites(): void
    {
        $alice = User::create();
        $bob = User::create();
        [$first, $second, $third] = [Post::create(), Post::create(), Post::create()];

        $alice->favorite($first);
        $alice->favorite($third);
        $bob->favorite($second);
        Bot::create()->favorite($second);

        $this->assertSame([$first->id, $third->id], Post::whereFavoritedBy($alice)->orderBy('id')->pluck('id')->map(function ($value) {
            return (int) $value;
        })->all());
        $this->assertSame([$second->id], Post::whereFavoritedBy($bob)->pluck('id')->map(function ($value) {
            return (int) $value;
        })->all());
        $this->assertSame(0, Comment::whereFavoritedBy($alice)->count());
    }

    public function test_a_feed_can_flag_what_the_viewer_favorited_in_the_same_query(): void
    {
        $viewer = User::create();
        [$first, $second] = [Post::create(), Post::create()];
        $viewer->favorite($second);
        User::create()->favorite($first);

        $posts = Post::withCount([
            'favorites',
            'favorites as favorited_by_viewer' => function ($query) use ($viewer) {
                $query->whereFavoriter($viewer);
            },
        ])->orderBy('id')->get();

        $this->assertSame([0, 1], $posts->pluck('favorited_by_viewer')->map(function ($value) {
            return (int) $value;
        })->all());
        $this->assertSame([1, 1], $posts->pluck('favorites_count')->map(function ($value) {
            return (int) $value;
        })->all());
    }

    public function test_favorite_scopes_filter_by_favoriter_and_favoritable(): void
    {
        $user = User::create();
        $post = Post::create();
        $user->favorite($post);
        Bot::create()->favorite($post);

        $this->assertSame(1, Favorite::whereFavoriter($user)->count());
        $this->assertSame(2, Favorite::whereFavoritable($post)->count());
        $this->assertSame(1, User::whereHas('givenFavorites', function ($query) use ($post) {
            $query->whereFavoritable($post);
        })->count());
    }

    public function test_has_and_where_has_count_the_favorites_in_sql(): void
    {
        $user = User::create();
        $bot = Bot::create();
        [$first, $second] = [Post::create(), Post::create()];
        Post::create();
        $user->favorite($first);
        $bot->favorite($first);
        $user->favorite($second);

        $this->assertSame(2, Post::has('favorites')->count());
        $this->assertSame(1, Post::has('favorites', '>=', 2)->count());
        $this->assertSame(1, Post::doesntHave('favorites')->count());
        $this->assertSame(1, Post::whereHas('favorites', function ($query) use ($bot) {
            $query->whereFavoriter($bot);
        })->count());
        $this->assertSame(1, User::has('givenFavorites')->count());
    }

    public function test_the_documented_usage_runs(): void
    {
        $user = User::create();
        $post = Post::create();
        Post::create();
        $user->favorite(Comment::create());

        $this->assertTrue($user->favorite($post));
        $this->assertTrue($user->hasFavorited($post));
        $this->assertFalse($user->toggleFavorite($post));
        $this->assertTrue($user->favorite($post));

        $posts = Post::query()
            ->whereFavoritedBy($user)
            ->withCount('favorites')
            ->latest()
            ->paginate();

        $received = $post->favorites()->with('favoriter')->latest()->get();
        $given = $user->givenFavorites()->with('favoritable')->latest()->get();

        $favoritePosts = $user->givenFavorites()
            ->where('favoritable_type', (new Post)->getMorphClass())
            ->with('favoritable')
            ->get();

        $this->assertSame(1, $posts->total());
        $this->assertTrue($posts->first()->is($post));
        $this->assertSame(1, (int) $posts->first()->getAttribute('favorites_count'));
        $this->assertCount(1, $received);
        $this->assertTrue($received->first()->favoriter->is($user));
        $this->assertCount(2, $given);
        $this->assertCount(1, $favoritePosts);
        $this->assertTrue($favoritePosts->first()->favoritable->is($post));
    }
}
