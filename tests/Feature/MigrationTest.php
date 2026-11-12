<?php

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;

test('migrate creates the favorites table', function () {
    $this->assertTrue(Schema::hasTable('favorites'));
    $this->assertTrue(Schema::hasColumns('favorites', [
        'id', 'favoriter_type', 'favoriter_id', 'favoritable_type', 'favoritable_id', 'created_at', 'updated_at',
    ]));
    $this->assertSame(1, DB::table('migrations')->where('migration', FAVORITES_MIGRATION)->count());
});

test('the unique index refuses a second row for a pair', function () {
    $row = ['favoriter_type' => 'user', 'favoriter_id' => '1', 'favoritable_type' => 'post', 'favoritable_id' => '1'];
    DB::table('favorites')->insert($row);

    $this->expectException(QueryException::class);

    DB::table('favorites')->insert($row);
});

test('the indexes cover the pair and the favoritable', function () {
    $indexes = tableIndexes('favorites');

    // Its first two columns also serve the favoriter's own lookups.
    expect($indexes['favorites_unique_pair'] ?? null)->toBe([
        'columns' => ['favoriter_type', 'favoriter_id', 'favoritable_type', 'favoritable_id'],
        'unique' => true,
    ])->and($indexes['favorites_favoritable_index'] ?? null)->toBe([
        'columns' => ['favoritable_type', 'favoritable_id'],
        'unique' => false,
    ]);
});

test('the unique index allows the same ids with other types', function () {
    DB::table('favorites')->insert(['favoriter_type' => 'user', 'favoriter_id' => '1', 'favoritable_type' => 'post', 'favoritable_id' => '1']);
    DB::table('favorites')->insert(['favoriter_type' => 'user', 'favoriter_id' => '1', 'favoritable_type' => 'comment', 'favoritable_id' => '1']);
    DB::table('favorites')->insert(['favoriter_type' => 'bot', 'favoriter_id' => '1', 'favoritable_type' => 'post', 'favoritable_id' => '1']);

    $this->assertSame(3, DB::table('favorites')->count());
});

test('rollback drops the table and migrate creates it again', function () {
    runArtisan('migrate:rollback');
    $this->assertFalse(Schema::hasTable('favorites'));

    runArtisan('migrate');
    $this->assertTrue(Schema::hasTable('favorites'));
    $this->assertTrue(User::create()->favorite(Post::create()));
});

test('the configured table name is used by the migration and the model', function () {
    runArtisan('migrate:rollback');
    $this->app['config']->set('pharaonic.favorites.table', 'post_favorites');
    runArtisan('migrate');

    $this->assertTrue(Schema::hasTable('post_favorites'));
    $this->assertFalse(Schema::hasTable('favorites'));
    $this->assertSame('post_favorites', (new Favorite)->getTable());

    $user = User::create();
    $post = Post::create();
    $this->assertTrue($user->favorite($post));
    $this->assertFalse($user->favorite($post));
    $this->assertSame(1, DB::table('post_favorites')->count());
    $this->assertSame(1, Post::whereFavoritedBy($user)->count());
    $this->assertSame(1, (int) Post::withCount('favorites')->firstOrFail()->getAttribute('favorites_count'));

    $indexes = tableIndexes('post_favorites');
    $this->assertArrayHasKey('post_favorites_unique_pair', $indexes);
    $this->assertArrayHasKey('post_favorites_favoritable_index', $indexes);
});

test('the migration leaves the tables of the likes and follows packages alone', function () {
    runArtisan('migrate:rollback');

    // Shaped like the pharaonic/laravel-likes and pharaonic/laravel-follows tables,
    // whose index names would clash on PostgreSQL if they were not prefixed.
    foreach (['likes' => 'liker', 'follows' => 'follower'] as $name => $side) {
        Schema::create($name, function (Blueprint $table) use ($name, $side) {
            $table->bigIncrements('id');
            $table->string($side.'_type', 191);
            $table->string($side.'_id', 64);
            $table->unique([$side.'_type', $side.'_id'], $name.'_unique_pair');
        });
    }

    runArtisan('migrate');

    $this->assertTrue(User::create()->favorite(Post::create()));
    $this->assertSame(1, DB::table('favorites')->count());
    $this->assertSame(0, DB::table('likes')->count());
    $this->assertSame(0, DB::table('follows')->count());
    $this->assertArrayHasKey('likes_unique_pair', tableIndexes('likes'));
    $this->assertArrayHasKey('follows_unique_pair', tableIndexes('follows'));
});

test('an existing favorites table stops the migration with an explanation', function () {
    runArtisan('migrate:rollback');

    // A table another package or an earlier migration created: no unique index.
    Schema::create('favorites', function (Blueprint $table) {
        $table->bigIncrements('id');
        $table->string('favoritable_id');
        $table->string('favoritable_type');
        $table->string('favoriter_id');
        $table->string('favoriter_type');
        $table->timestamps();
    });

    try {
        runArtisan('migrate');
        $this->fail('The migration ran over an existing table.');
    } catch (RuntimeException $exception) {
        $this->assertStringContainsString('The [favorites] table already exists', $exception->getMessage());
        $this->assertStringContainsString('Set pharaonic.favorites.table to another name', $exception->getMessage());
    }

    $this->assertSame(0, DB::table('migrations')->where('migration', FAVORITES_MIGRATION)->count());
});
