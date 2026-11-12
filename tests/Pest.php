<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Favorites\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * The package migration, as Laravel records it.
 */
const FAVORITES_MIGRATION = '2026_11_12_000001_create_pharaonic_favorites_table';

/**
 * Run an Artisan command and expect it to succeed.
 *
 * @param  array<string, mixed>  $parameters
 */
function runArtisan(string $command, array $parameters = []): void
{
    expect(app(Kernel::class)->call($command, $parameters))->toBe(0);
}

/**
 * The indexes of a table, by name: their columns and whether they are unique.
 * Schema::getIndexes() is missing before Laravel 10.x, where only SQLite is read.
 *
 * @return array<string, array{columns: array<int, string>, unique: bool}>
 */
function tableIndexes(string $table): array
{
    $indexes = [];

    if (method_exists(Schema::getFacadeRoot(), 'getIndexes')) {
        foreach (Schema::getIndexes($table) as $index) {
            $indexes[$index['name']] = ['columns' => $index['columns'], 'unique' => (bool) $index['unique']];
        }

        return $indexes;
    }

    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('Reading indexes needs Schema::getIndexes() or SQLite.');
    }

    foreach (DB::select('select name, "unique" from pragma_index_list(?)', [$table]) as $index) {
        $columns = array_map(function ($column) {
            return $column->name;
        }, DB::select('select name from pragma_index_info(?) order by seqno', [$index->name]));

        $indexes[$index->name] = ['columns' => $columns, 'unique' => (bool) $index->unique];
    }

    return $indexes;
}
