<?php

namespace Pharaonic\Laravel\Favorites\Tests\Feature;

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
use Pharaonic\Laravel\Favorites\Tests\TestCase;

class IntegrityTest extends TestCase
{
    public function test_a_favorite_written_by_another_connection_is_reported_as_already_favorited(): void
    {
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
    }

    public function test_a_favorite_committed_during_the_callers_transaction_is_found_not_thrown(): void
    {
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
    }

    public function test_a_violation_of_another_unique_index_is_not_swallowed(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->unique(['favoriter_type', 'favoriter_id'], 'favorites_one_per_favoriter');
        });

        $user = User::create();
        $this->assertTrue($user->favorite(Post::create()));

        $this->expectException(QueryException::class);

        $user->favorite(Post::create());
    }

    public function test_other_database_errors_are_not_swallowed(): void
    {
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
    }

    public function test_an_already_favorited_pair_leaves_the_callers_transaction_usable(): void
    {
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
    }

    public function test_a_rolled_back_transaction_rolls_the_favorite_back(): void
    {
        $user = User::create();
        $post = Post::create();

        DB::beginTransaction();
        $this->assertTrue($user->favorite($post));
        DB::rollBack();

        $this->assertFalse($user->hasFavorited($post));
    }

    public function test_a_model_event_can_cancel_the_insert(): void
    {
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
    }

    public function test_a_deadlock_outside_a_transaction_is_retried(): void
    {
        $attempts = $this->deadlockOnCreating(1);

        Event::fake([FavoriteCreated::class]);
        $user = User::create();
        $post = Post::create();

        $this->assertTrue($user->favorite($post));
        $this->assertSame(2, $attempts->count);
        $this->assertSame(1, Favorite::query()->count());
        Event::assertDispatchedTimes(FavoriteCreated::class, 1);
    }

    public function test_a_deadlock_that_keeps_happening_is_thrown(): void
    {
        $attempts = $this->deadlockOnCreating(PHP_INT_MAX);
        $user = User::create();
        $post = Post::create();

        try {
            $user->favorite($post);
            $this->fail('The deadlock was swallowed.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Deadlock found', $exception->getMessage());
        }

        $this->assertSame(3, $attempts->count);
        $this->assertSame(0, Favorite::query()->count());
    }

    public function test_a_deadlock_inside_a_transaction_is_left_to_the_caller(): void
    {
        $attempts = $this->deadlockOnCreating(1);
        $user = User::create();
        $post = Post::create();

        DB::beginTransaction();

        try {
            $user->favorite($post);
            $this->fail('The deadlock was retried inside the caller\'s transaction.');
        } catch (\PDOException $exception) {
            // Laravel 11+ rethrows it as DeadlockException, so the caller's DB::transaction() retries.
            $this->assertStringContainsString('Deadlock found', $exception->getMessage());
        } finally {
            DB::rollBack();
        }

        $this->assertSame(1, $attempts->count);
    }

    /**
     * Make the first $times inserts of a favorite fail as MySQL's deadlock victim.
     */
    private function deadlockOnCreating(int $times): \stdClass
    {
        $attempts = new \stdClass;
        $attempts->count = 0;

        Favorite::creating(function () use ($attempts, $times) {
            if (++$attempts->count > $times) {
                return;
            }

            $message = 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction';
            $pdo = new \PDOException($message);
            $pdo->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock; try restarting transaction'];

            throw new QueryException('insert into `favorites` ...', [], $pdo);
        });

        return $attempts;
    }
}
