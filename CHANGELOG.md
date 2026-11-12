# Changelog

All notable changes to this project are documented here.

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
