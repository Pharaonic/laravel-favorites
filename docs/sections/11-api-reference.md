## API Reference

### `CanFavorite`

`Pharaonic\Laravel\Favorites\Concerns\CanFavorite`, for the model that favorites.

| Method | Returns | Description |
| --- | --- | --- |
| `favorite(Model $favoritable)` | `bool` | Favorites the model. True when this call created the favorite, false when it already existed. |
| `unfavorite(Model $favoritable)` | `bool` | Removes the favorite. True when this call removed it, false when there was none. |
| `hasFavorited(Model $favoritable)` | `bool` | Whether this model favorites the model. False when either is unsaved. |
| `toggleFavorite(Model $favoritable)` | `bool` | Favorites the model, or removes the favorite. Returns the new state: true when favorited. |
| `givenFavorites()` | `MorphMany` | The favorites this model gave. |

### `HasFavorites`

`Pharaonic\Laravel\Favorites\Concerns\HasFavorites`, for the model that is favorited.

| Method | Returns | Description |
| --- | --- | --- |
| `favorites()` | `MorphMany` | The favorites this model received. |
| `favoriteBy(Model $favoriter)` | `bool` | Same as `$favoriter->favorite($this)`, for any favoriter model. |
| `unfavoriteBy(Model $favoriter)` | `bool` | Same as `$favoriter->unfavorite($this)`. |
| `favoritedBy(Model $favoriter)` | `bool` | Same as `$favoriter->hasFavorited($this)`. |
| `whereFavoritedBy(Model $favoriter)` | scope | Keeps the models the favoriter favorites: `Post::whereFavoritedBy($user)`. |

### `Favorite`

`Pharaonic\Laravel\Favorites\Models\Favorite`.

| Member | Type | Description |
| --- | --- | --- |
| `favoriter()` | `MorphTo` | The model that gave the favorite. |
| `favoritable()` | `MorphTo` | The model that received the favorite. |
| `whereFavoriter(Model $favoriter)` | scope | Favorites given by the model. Matches nothing for an unsaved model. |
| `whereFavoritable(Model $favoritable)` | scope | Favorites received by the model. Matches nothing for an unsaved model. |
| `id` | `int` | Primary key. |
| `favoriter_type`, `favoritable_type` | `string` | `getMorphClass()` of each end, up to 191 characters. |
| `favoriter_id`, `favoritable_id` | `string` | The key of each end as a string, up to 64 characters. |
| `created_at`, `updated_at` | `Carbon` | Timestamps. |

### Events

| Event | Properties | Dispatched |
| --- | --- | --- |
| `Pharaonic\Laravel\Favorites\Events\FavoriteCreated` | `Favorite $favorite`, `Model $favoriter`, `Model $favoritable` | After a call inserted a favorite. |
| `Pharaonic\Laravel\Favorites\Events\FavoriteRemoved` | `Favorite $favorite`, `Model $favoriter`, `Model $favoritable` | After a call deleted a favorite (not on cleanup). |

### Exceptions

| Exception | Thrown by | When |
| --- | --- | --- |
| `InvalidArgumentException` | `favorite()`, `unfavorite()`, `toggleFavorite()`, `favoriteBy()`, `unfavoriteBy()` | A model is unsaved or has no key, a key is longer than 64 characters, or a morph type is longer than 191. Nothing is written. |
| `Illuminate\Database\ClassMorphViolationException` | every method | `Relation::enforceMorphMap()` is on and a model has no alias. Nothing is written. |
| `Illuminate\Database\QueryException` | every method | Any database error other than the pair's own unique index refusing a duplicate. |

### Configuration and publish tags

| Key | Default |
| --- | --- |
| `pharaonic.favorites.table` | `favorites` |
| `pharaonic.favorites.cleanup_on_delete` | `true` |

| Tag | Publishes |
| --- | --- |
| `favorites-config`, `pharaonic-config` | `config/pharaonic/favorites.php` |
| `favorites-migrations`, `pharaonic-migrations` | `database/migrations/2026_11_12_000001_create_pharaonic_favorites_table.php` |
| `laravel-favorites`, `pharaonic` | both |
