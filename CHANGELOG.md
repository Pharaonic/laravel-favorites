# Changelog

All notable changes to this project are documented here.

## 12.0.0 - 2026-11-12

First release of the Laravel 12 line, built from 11.0.0.

### Compatibility

- Laravel 12 on PHP 8.2, 8.3, 8.4 or 8.5.
- The same API and behavior as 11.0.0.

## 11.0.0 - 2026-11-12

First release of the Laravel 11 line, built from 10.0.0.

### Compatibility

- Laravel 11 on PHP 8.2, 8.3 or 8.4. PHP 8.1 is no longer supported on this line.
- The same API and behavior as 10.0.0. The relations and scopes declare their generic types (`MorphMany<Favorite, $this>`, `MorphTo<Model, $this>`) for static analysis.

## 10.0.0 - 2026-11-12

First release of the Laravel 10 line, built from 9.0.0.

### Compatibility

- Laravel 10 on PHP 8.1, 8.2 or 8.3. PHP 8.0 is no longer supported on this line.
- The same API and behavior as 9.0.0.

## 9.0.0 - 2026-11-12

First release of the Laravel 9 line, built from 8.0.0.

### Compatibility

- Laravel 9.33+ on PHP 8.0, 8.1 or 8.2. Laravel 9.33 is the first 9.x release that requires a Carbon release (2.62.1+) working on PHP 8.2; with older ones, every date method throws a `TypeError` on PHP 8.2.
- The same API and behavior as 8.0.0.

## 8.0.0 - 2026-11-12

First release of the Laravel 8 line, built from 7.0.0.

### Compatibility

- Laravel 8.75+ on PHP 8.0 or 8.1.
- The same API and behavior as 7.0.0. With `Relation::enforceMorphMap()`, favoriting with an unmapped model throws Laravel's `ClassMorphViolationException` before anything is written.

## 7.0.0 - 2026-11-12

First release of the Laravel 7 line, built from 6.0.0.

### Compatibility

- Laravel 7.30+ on PHP 8.0.
- The same API and behavior as 6.0.0.

## 6.0.0 - 2026-11-12

### Added

- `CanFavorite` for the model that favorites: `favorite()`, `unfavorite()`, `hasFavorited()`, `toggleFavorite()`, and the `givenFavorites()` relation.
- `HasFavorites` for the model that is favorited: `favoriteBy()`, `unfavoriteBy()`, `favoritedBy()`, the `favorites()` relation, and the `whereFavoritedBy()` scope. A model can use both traits.
- The `Favorite` model with `favoriter()` and `favoritable()` relations and `whereFavoriter()` and `whereFavoritable()` scopes.
- A `favorites` table whose unique index keeps each favoriter and favoritable pair to one row, with string ids so integer, UUID, and ULID keys share it.
- `FavoriteCreated` and `FavoriteRemoved` events, dispatched only when a call inserts or deletes a favorite.
- Favorites are deleted with the model that gave or received them, and kept when it is only soft deleted.
- `pharaonic.favorites.table` and `pharaonic.favorites.cleanup_on_delete` settings.

### Compatibility

- Laravel 6.20+ on PHP 8.0.
