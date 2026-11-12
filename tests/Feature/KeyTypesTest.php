<?php

namespace Pharaonic\Laravel\Favorites\Tests\Feature;

use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Sku;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\UlidModel;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\UuidModel;
use Pharaonic\Laravel\Favorites\Tests\TestCase;

class KeyTypesTest extends TestCase
{
    public function test_integer_uuid_and_ulid_keys_share_the_table(): void
    {
        $user = User::create();
        $uuid = UuidModel::create();
        $ulid = UlidModel::create();
        $post = Post::create();

        $this->assertSame(36, strlen($uuid->getKey()));
        $this->assertSame(26, strlen($ulid->getKey()));

        $this->assertTrue($user->favorite($uuid));
        $this->assertTrue($uuid->favorite($post));
        $this->assertTrue($ulid->favorite($uuid));
        $this->assertTrue($uuid->favorite($ulid));
        $this->assertFalse($ulid->favorite($uuid));

        $this->assertTrue($uuid->favoritedBy($user));
        $this->assertTrue($post->favoritedBy($uuid));
        $this->assertTrue($ulid->hasFavorited($uuid));
        $this->assertSame(4, Favorite::query()->count());

        $favorite = Favorite::whereFavoriter($ulid)->firstOrFail();
        $this->assertSame((string) $ulid->getKey(), $favorite->favoriter_id);
        $this->assertTrue($favorite->favoriter->is($ulid));
        $this->assertTrue($favorite->favoritable->is($uuid));
    }

    public function test_integer_keys_are_stored_as_strings(): void
    {
        $user = User::create();
        $post = Post::create();
        $user->favorite($post);

        $favorite = Favorite::query()->firstOrFail();

        $this->assertSame((string) $user->getKey(), $favorite->favoriter_id);
        $this->assertSame((string) $post->getKey(), $favorite->favoritable_id);
    }

    public function test_queries_work_on_string_keyed_parents(): void
    {
        $user = User::create();
        [$first, $second] = [UuidModel::create(), UuidModel::create()];
        $ulid = UlidModel::create();

        $user->favorite($first);
        $ulid->favorite($first);
        $ulid->favorite($second);

        $this->assertSame([$first->id], UuidModel::whereFavoritedBy($user)->pluck('id')->all());
        $this->assertSame(2, UuidModel::whereFavoritedBy($ulid)->count());

        $counts = UuidModel::withCount('favorites')->get()->pluck('favorites_count', 'id')->map(function ($value) {
            return (int) $value;
        });
        $this->assertSame(2, $counts[$first->id]);
        $this->assertSame(1, $counts[$second->id]);

        $models = UuidModel::with('favorites.favoriter')->get()->keyBy('id');
        $this->assertCount(2, $models[$first->id]->favorites);
        $this->assertCount(2, UlidModel::with('givenFavorites.favoritable')->firstOrFail()->givenFavorites);
    }

    public function test_string_keys_keep_their_leading_zeros(): void
    {
        $user = User::create();
        $padded = Sku::create(['id' => '000123']);
        $plain = Sku::create(['id' => '123']);

        $this->assertTrue($user->favorite($padded));
        $this->assertTrue($padded->favorite($user));

        $this->assertTrue($user->hasFavorited($padded));
        $this->assertFalse($user->hasFavorited($plain));
        $this->assertSame('000123', Favorite::whereFavoritable($padded)->firstOrFail()->favoritable_id);
        $this->assertSame('000123', Favorite::whereFavoriter($padded)->firstOrFail()->favoriter_id);

        $this->assertSame(['000123'], Sku::whereFavoritedBy($user)->pluck('id')->all());
        $this->assertSame(['000123'], Sku::has('favorites')->pluck('id')->all());
        $this->assertSame(['000123'], Sku::has('givenFavorites')->pluck('id')->all());

        $counts = Sku::withCount('favorites')->get()->pluck('favorites_count', 'id')->map(function ($value) {
            return (int) $value;
        });
        $this->assertSame(1, $counts['000123']);
        $this->assertSame(0, $counts['123']);

        $models = Sku::with('favorites')->get()->keyBy('id');
        $this->assertCount(1, $models['000123']->favorites);
        $this->assertCount(0, $models['123']->favorites);
        $this->assertTrue(Favorite::whereFavoriter($user)->firstOrFail()->favoritable->is($padded));
    }
}
