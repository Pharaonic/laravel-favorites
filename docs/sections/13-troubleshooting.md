## Troubleshooting

### `The [favorites] table already exists` when migrating

Another package or an earlier migration created a `favorites` table. Set `pharaonic.favorites.table` to another name before migrating, or move that table out of the way first.

### `favorite()` returns false the first time

The pair was already favorited: `favorite()` returns whether this call created the favorite. Use `hasFavorited()` for the current state. A `creating` listener on the `Favorite` model that returns false also makes `favorite()` return false.

### `The favoriter [App\Models\User] is not saved`

A write was called with a model that isn't saved. Save it first, or check `$model->exists`.

### `Register a shorter alias with Relation::morphMap()`

The class name is longer than the 191 characters the type columns hold. Add a morph map alias for the model.

### `Illegal mix of collations` on MySQL

Your model's table and the favorites table use different collations, and the model has a string key (UUID, ULID). Create both with the same collation, the `collation` of your database connection.

### `operator does not exist: bigint = character varying` on PostgreSQL

A query compares a model key with the string `favoritable_id` column directly, for example a hand-written join. Go through the package relations (`favorites()`, `givenFavorites()`, `whereFavoritedBy()`, `withCount('favorites')`), which compare the key as a string, or cast it yourself: `cast(posts.id as varchar) = favorites.favoritable_id`.

### Favorites stay after deleting models

The models were deleted with a query (`Post::where(...)->delete()`), which skips model events, or `cleanup_on_delete` is false. See [Deletes and Soft Deletes](#deletion-and-soft-deletes) for the manual cleanup.

### Favorites are missing after adding a morph map

Rows stored before the map hold class names, and the package now looks for the alias. Update those rows deliberately, type by type, for example `UPDATE favorites SET favoritable_type = 'post' WHERE favoritable_type = 'App\\Models\\Post';`.
