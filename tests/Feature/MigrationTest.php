<?php

namespace Pharaonic\Laravel\Favorites\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Favorites\Models\Favorite;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\Post;
use Pharaonic\Laravel\Favorites\Tests\Fixtures\User;
use Pharaonic\Laravel\Favorites\Tests\TestCase;
use RuntimeException;

class MigrationTest extends TestCase
{
    private const MIGRATION = '2026_11_12_000001_create_pharaonic_favorites_table';

    public function test_migrate_creates_the_favorites_table(): void
    {
        $this->assertTrue(Schema::hasTable('favorites'));
        $this->assertTrue(Schema::hasColumns('favorites', [
            'id', 'favoriter_type', 'favoriter_id', 'favoritable_type', 'favoritable_id', 'created_at', 'updated_at',
        ]));
        $this->assertSame(1, DB::table('migrations')->where('migration', self::MIGRATION)->count());
    }

    public function test_the_unique_index_refuses_a_second_row_for_a_pair(): void
    {
        $row = ['favoriter_type' => 'user', 'favoriter_id' => '1', 'favoritable_type' => 'post', 'favoritable_id' => '1'];
        DB::table('favorites')->insert($row);

        $this->expectException(QueryException::class);

        DB::table('favorites')->insert($row);
    }

    public function test_the_indexes_cover_the_pair_and_the_favoritable(): void
    {
        $indexes = $this->tableIndexes('favorites');

        // Its first two columns also serve the favoriter's own lookups.
        $this->assertSame([
            'columns' => ['favoriter_type', 'favoriter_id', 'favoritable_type', 'favoritable_id'],
            'unique' => true,
        ], $indexes['favorites_unique_pair'] ?? null);
        $this->assertSame([
            'columns' => ['favoritable_type', 'favoritable_id'],
            'unique' => false,
        ], $indexes['favorites_favoritable_index'] ?? null);
    }

    public function test_the_unique_index_allows_the_same_ids_with_other_types(): void
    {
        DB::table('favorites')->insert(['favoriter_type' => 'user', 'favoriter_id' => '1', 'favoritable_type' => 'post', 'favoritable_id' => '1']);
        DB::table('favorites')->insert(['favoriter_type' => 'user', 'favoriter_id' => '1', 'favoritable_type' => 'comment', 'favoritable_id' => '1']);
        DB::table('favorites')->insert(['favoriter_type' => 'bot', 'favoriter_id' => '1', 'favoritable_type' => 'post', 'favoritable_id' => '1']);

        $this->assertSame(3, DB::table('favorites')->count());
    }

    public function test_rollback_drops_the_table_and_migrate_creates_it_again(): void
    {
        $this->artisanCall('migrate:rollback');
        $this->assertFalse(Schema::hasTable('favorites'));

        $this->artisanCall('migrate');
        $this->assertTrue(Schema::hasTable('favorites'));
        $this->assertTrue(User::create()->favorite(Post::create()));
    }

    public function test_the_configured_table_name_is_used_by_the_migration_and_the_model(): void
    {
        $this->artisanCall('migrate:rollback');
        $this->app['config']->set('pharaonic.favorites.table', 'post_favorites');
        $this->artisanCall('migrate');

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

        $indexes = $this->tableIndexes('post_favorites');
        $this->assertArrayHasKey('post_favorites_unique_pair', $indexes);
        $this->assertArrayHasKey('post_favorites_favoritable_index', $indexes);
    }

    public function test_the_migration_leaves_the_tables_of_the_likes_and_follows_packages_alone(): void
    {
        $this->artisanCall('migrate:rollback');

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

        $this->artisanCall('migrate');

        $this->assertTrue(User::create()->favorite(Post::create()));
        $this->assertSame(1, DB::table('favorites')->count());
        $this->assertSame(0, DB::table('likes')->count());
        $this->assertSame(0, DB::table('follows')->count());
        $this->assertArrayHasKey('likes_unique_pair', $this->tableIndexes('likes'));
        $this->assertArrayHasKey('follows_unique_pair', $this->tableIndexes('follows'));
    }

    public function test_an_existing_favorites_table_stops_the_migration_with_an_explanation(): void
    {
        $this->artisanCall('migrate:rollback');

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
            $this->artisanCall('migrate');
            $this->fail('The migration ran over an existing table.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('The [favorites] table already exists', $exception->getMessage());
            $this->assertStringContainsString('Set pharaonic.favorites.table to another name', $exception->getMessage());
        }

        $this->assertSame(0, DB::table('migrations')->where('migration', self::MIGRATION)->count());
    }

    /**
     * The indexes of a table, by name: their columns and whether they are unique.
     * Schema::getIndexes() is missing before Laravel 10.x, where only SQLite is read.
     *
     * @return array<string, array{columns: array<int, string>, unique: bool}>
     */
    private function tableIndexes(string $table): array
    {
        $indexes = [];

        if (method_exists(Schema::getFacadeRoot(), 'getIndexes')) {
            foreach (Schema::getIndexes($table) as $index) {
                $indexes[$index['name']] = ['columns' => $index['columns'], 'unique' => (bool) $index['unique']];
            }

            return $indexes;
        }

        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Reading indexes needs Schema::getIndexes() or SQLite.');
        }

        foreach (DB::select('select name, "unique" from pragma_index_list(?)', [$table]) as $index) {
            $columns = array_map(function ($column) {
                return $column->name;
            }, DB::select('select name from pragma_index_info(?) order by seqno', [$index->name]));

            $indexes[$index->name] = ['columns' => $columns, 'unique' => (bool) $index->unique];
        }

        return $indexes;
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function artisanCall(string $command, array $parameters = []): void
    {
        $this->assertSame(0, $this->app->make(Kernel::class)->call($command, $parameters));
    }
}
