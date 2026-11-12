<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Favorites\Events\FavoriteCreated;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;

test('a favorite written by another connection is reported as already favorited', function () {
    $this->useSharedDatabase();
    $user = User::create();
    $post = Post::create();

    // A second, independent connection to the same database, as another request would have.
    $this->app['config']->set('database.connections.other', $this->app['config']->get('database.connections.testing'));
    DB::connection('other')->table('favorites')->insert([
        'favoriter_type' => User::class,
        'favoriter_id' => (string) $user->getKey(),
        'favoritable_type' => Post::class,
        'favoritable_id' => (string) $post->getKey(),
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);
    DB::disconnect('other');

    Event::fake([FavoriteCreated::class]);

    // favorite() does not read first: the insert hits the unique index.
    $this->assertFalse($user->favorite($post));
    $this->assertSame(1, Favorite::query()->count());
    Event::assertNotDispatched(FavoriteCreated::class);
});

test('a favorite committed during the callers transaction is found, not thrown', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite lets one connection write at a time.');
    }

    $user = User::create();
    $post = Post::create();
    $this->app['config']->set('database.connections.other', $this->app['config']->get('database.connections.testing'));

    DB::transaction(function () use ($user, $post) {
        // MySQL (REPEATABLE READ) takes the transaction's snapshot at its first read.
        $this->assertFalse($user->hasFavorited($post));

        DB::connection('other')->table('favorites')->insert([
            'favoriter_type' => User::class,
            'favoriter_id' => (string) $user->getKey(),
            'favoritable_type' => Post::class,
            'favoritable_id' => (string) $post->getKey(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $this->assertFalse($user->favorite($post));
        $this->assertFalse($user->toggleFavorite($post));
    });
    DB::disconnect('other');

    $this->assertFalse($user->hasFavorited($post));
    $this->assertSame(0, Favorite::query()->count());
});

test('a violation of another unique index is not swallowed', function () {
    Schema::table('favorites', function (Blueprint $table) {
        $table->unique(['favoriter_type', 'favoriter_id'], 'favorites_one_per_favoriter');
    });

    $user = User::create();
    $this->assertTrue($user->favorite(Post::create()));

    $this->expectException(QueryException::class);

    $user->favorite(Post::create());
});

test('other database errors are not swallowed', function () {
    $user = User::create();
    $post = Post::create();
    Schema::drop('favorites');

    foreach (['favorite', 'unfavorite', 'hasFavorited', 'toggleFavorite'] as $method) {
        try {
            $user->{$method}($post);
            $this->fail("{$method}() hid a missing table.");
        } catch (QueryException $exception) {
            $this->assertStringContainsString('favorites', $exception->getMessage());
        }
    }
});

test('an already favorited pair leaves the callers transaction usable', function () {
    $user = User::create();
    $post = Post::create();
    $user->favorite($post);

    DB::transaction(function () use ($user, $post) {
        $this->assertFalse($user->favorite($post));
        $this->assertFalse($user->toggleFavorite($post));
        $this->assertTrue($user->favorite($post));
        $this->assertSame(1, Post::query()->count());
    });

    $this->assertTrue($user->hasFavorited($post));
});

test('a rolled back transaction rolls the favorite back', function () {
    $user = User::create();
    $post = Post::create();

    DB::beginTransaction();
    $this->assertTrue($user->favorite($post));
    DB::rollBack();

    $this->assertFalse($user->hasFavorited($post));
});

test('a model event can cancel the insert', function () {
    Favorite::creating(function (Model $favorite) {
        return false;
    });

    Event::fake([FavoriteCreated::class]);
    $user = User::create();
    $post = Post::create();

    $this->assertFalse($user->favorite($post));
    $this->assertFalse($user->toggleFavorite($post));
    $this->assertSame(0, Favorite::query()->count());
    Event::assertNotDispatched(FavoriteCreated::class);
});

/**
 * Make the first $times inserts of a favorite fail as MySQL's deadlock victim.
 */
function deadlockOnCreating(int $times): stdClass
{
    $attempts = new stdClass;
    $attempts->count = 0;

    Favorite::creating(function () use ($attempts, $times) {
        if (++$attempts->count > $times) {
            return;
        }

        $message = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';
        $pdo = new PDOException($message);
        $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];

        throw new QueryException('testing', 'insert into `favorites` ...', [], $pdo);
    });

    return $attempts;
}

test('a deadlock outside a transaction is retried', function () {
    $attempts = deadlockOnCreating(1);

    Event::fake([FavoriteCreated::class]);
    $user = User::create();
    $post = Post::create();

    expect($user->favorite($post))->toBeTrue()
        ->and($attempts->count)->toBe(2)
        ->and(Favorite::query()->count())->toBe(1);
    Event::assertDispatchedTimes(FavoriteCreated::class, 1);
});

test('a deadlock that keeps happening is thrown', function () {
    $attempts = deadlockOnCreating(PHP_INT_MAX);
    $user = User::create();
    $post = Post::create();

    expect(fn () => $user->favorite($post))->toThrow(QueryException::class, 'Deadlock found')
        ->and($attempts->count)->toBe(3)
        ->and(Favorite::query()->count())->toBe(0);
});

test('a deadlock inside a transaction is left to the caller', function () {
    $attempts = deadlockOnCreating(1);
    $user = User::create();
    $post = Post::create();

    DB::beginTransaction();

    try {
        // Laravel 11+ rethrows it as DeadlockException, so the caller's DB::transaction() retries.
        expect(fn () => $user->favorite($post))->toThrow(PDOException::class, 'Deadlock found');
    } finally {
        DB::rollBack();
    }

    expect($attempts->count)->toBe(1);
});
