# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Polymorphic favorites between any two Eloquent models: `CanFavorite` for the model that favorites, `HasFavorites` for the model that is favorited, one `favorites` table whose unique index keeps each pair to one row.

## Supported Laravel lines

One branch per Laravel major, from `6.x` to the newest major. Each line's PHP range, `illuminate/*` constraint, and tools follow the Pharaonic Laravel line table. `6.x` runs PHPUnit; later lines run Pest.

## Core library

None. Everything the package does is Eloquent glue.

## Public API invariants

Configuration keys (`pharaonic.favorites.*`), publish tags, and registered Blade, Artisan, macro, and validation names are public API.

- Configuration: `pharaonic.favorites.table` (default `favorites`) and `pharaonic.favorites.cleanup_on_delete` (default `true`).
- Publish tags: `pharaonic`, `laravel-favorites`, `pharaonic-config`, `favorites-config`, `pharaonic-migrations`, and `favorites-migrations`.
- `CanFavorite`: `favorite()`, `unfavorite()`, `hasFavorited()`, `toggleFavorite()`, `givenFavorites()`. `HasFavorites`: `favorites()`, `favoriteBy()`, `unfavoriteBy()`, `favoritedBy()`, `scopeWhereFavoritedBy()`. Neither trait defines a method of the other, so a model can use both.
- `favorite()`, `favoriteBy()`, `unfavorite()`, and `unfavoriteBy()` return whether the call changed the table; `toggleFavorite()` returns the state it left. `hasFavorited()` and `favoritedBy()` return false for an unsaved model; the writes throw `InvalidArgumentException` for one.
- `Models\Favorite` with `favoriter()`, `favoritable()`, `scopeWhereFavoriter()`, `scopeWhereFavoritable()`, and the columns `favoriter_type`, `favoriter_id`, `favoritable_type`, `favoritable_id`.
- `Events\FavoriteCreated` and `Events\FavoriteRemoved`, each with public `$favorite`, `$favoriter`, and `$favoritable`, dispatched only when the call inserted or deleted the row.
- `Support\FavoriteManager` and `Relations\FavoritesMorphMany` are internal.

## Package-specific constraints

- The migration file name `2026_11_12_000001_create_pharaonic_favorites_table.php` and its class `CreatePharaonicFavoritesTable` never change. The class is named because `6.x` supports Laravel below 8.37, and it carries `Pharaonic` so it never collides with an application's own `CreateFavoritesTable`.
- Later schema changes go in new migrations, never in the released one.
- The migration refuses to run over an existing table: one another package or migration created would lack the unique index.
- Index names start with the table name (`{table}_unique_pair`, `{table}_favoritable_index`), because PostgreSQL index names are unique per schema and `pharaonic/laravel-likes` and `pharaonic/laravel-follows` use the same suffixes. The unique index leads with the favoriter columns, so it also serves `givenFavorites()` and the favoriter cleanup; `{table}_favoritable_index` serves `favorites()`, `withCount('favorites')`, and the favoritable cleanup.
- Uniqueness comes from the `{table}_unique_pair` index, never from a read before the write. A unique violation counts as "already favorited" only when the pair's row can be read afterwards; otherwise the insert is retried, then the exception is rethrown. Inside a transaction that read is a locking read (`sharedLock()`): a plain read under MySQL's REPEATABLE READ returns the transaction's snapshot and misses a row another request committed since, so the duplicate would be thrown instead of reported.
- A deadlock (SQLSTATE `40001` or `40P01`, MySQL error 1213) on the insert or delete runs the statement again, up to three times, but only outside a transaction: inside one, the database already rolled the caller's transaction back.
- Ids are stored as strings (`varchar(64)`), so the favorites relations compare the parent key as a string in `has()`, `whereHas()`, and `withCount()` (`FavoritesMorphMany`): PostgreSQL refuses `bigint = varchar` and `uuid = varchar`. MySQL uses `CONCAT()` rather than `CAST()`, which fails when the connection collation differs from the table's.
- Events are dispatched right after the write, inside the caller's transaction when there is one, because `ShouldDispatchAfterCommit` needs Laravel 10.30.
- `phpstan.neon.dist` also analyses `tests/Fixtures`: PHPStan analyses a trait only through a class that uses it.
- `toggleFavorite()` inserts first and deletes the row the unique index points at. Nothing serializes the two steps, so concurrent toggles of one pair are not linearizable; the tests only promise that they never leave two rows.
- `ConcurrencyTest` runs separate PHP processes (`tests/Processes/worker.php`) against a shared database: a SQLite file by default, MySQL or PostgreSQL through the `FAVORITES_DB*` environment variables (`.github/workflows/databases.yml`).
- The published-migration test needs a process where the migration class was never declared. `6.x` runs it as a PHPUnit class in separate processes; Pest cannot, so later lines run `tests/Processes/published-migration.php` as a subprocess.

## Branch notes

Fixes land on the oldest affected line and are ported to every later line and `develop`.

## Documentation

`docs/`, published to https://pharaonic.dev/packages/laravel/favorites on every stable release.

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
