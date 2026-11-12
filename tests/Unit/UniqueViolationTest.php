<?php

use Illuminate\Database\QueryException;
use Pharaonic\Laravel\Favorites\Support\FavoriteManager;

test('only unique violations are recognized', function (string $sqlState, ?int $driverCode, string $message, bool $unique) {
    $pdo = new PDOException($message);
    $pdo->errorInfo = [$sqlState, $driverCode, $message];

    expect(FavoriteManager::isUniqueViolation(new QueryException('testing', 'insert into "favorites" ...', [], $pdo)))->toBe($unique);
})->with([
    'mysql duplicate entry' => ['23000', 1062, "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x' for key 'favorites_unique_pair'", true],
    'mysql not null' => ['23000', 1048, "SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'favoriter_id' cannot be null", false],
    'mysql foreign key' => ['23000', 1452, 'SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row', false],
    'mysql gone away' => ['HY000', 2006, 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away', false],
    'pgsql unique violation' => ['23505', 7, 'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint "favorites_unique_pair"', true],
    'pgsql not null' => ['23502', 7, 'SQLSTATE[23502]: Not null violation: 7 ERROR: null value in column "favoriter_id"', false],
    'sqlite unique' => ['23000', 19, 'SQLSTATE[23000]: Integrity constraint violation: 19 UNIQUE constraint failed: favorites.favoriter_type, favorites.favoriter_id, favorites.favoritable_type, favorites.favoritable_id', true],
    'sqlite not null' => ['23000', 19, 'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: favorites.favoriter_id', false],
    'sqlite locked' => ['HY000', 5, 'SQLSTATE[HY000]: General error: 5 database is locked', false],
    'sqlsrv duplicate key' => ['23000', 2627, 'SQLSTATE[23000]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Violation of UNIQUE KEY constraint', true],
    'sqlsrv unique index' => ['23000', 2601, 'SQLSTATE[23000]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Cannot insert duplicate key row', true],
]);

test('only deadlocks are recognized', function (string $sqlState, ?int $driverCode, string $message, bool $deadlock) {
    $pdo = new PDOException($message);
    $pdo->errorInfo = [$sqlState, $driverCode, $message];

    expect(FavoriteManager::isDeadlock(new QueryException('testing', 'insert into "favorites" ...', [], $pdo)))->toBe($deadlock);
})->with([
    'mysql deadlock' => ['40001', 1213, 'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock', true],
    'mysql lock wait timeout' => ['HY000', 1205, 'SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded', false],
    'mysql duplicate entry' => ['23000', 1062, "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'x'", false],
    'pgsql deadlock' => ['40P01', 7, 'SQLSTATE[40P01]: Deadlock detected: 7 ERROR: deadlock detected', true],
    'pgsql serialization failure' => ['40001', 7, 'SQLSTATE[40001]: Serialization failure: 7 ERROR: could not serialize access', true],
    'pgsql unique violation' => ['23505', 7, 'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value', false],
    'sqlite locked' => ['HY000', 5, 'SQLSTATE[HY000]: General error: 5 database is locked', false],
    'sqlsrv deadlock victim' => ['40001', 1205, 'SQLSTATE[40001]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Transaction was deadlocked', true],
]);
