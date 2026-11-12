## Deletes and Soft Deletes

When a model that uses `CanFavorite` or `HasFavorites` is deleted through Eloquent, its favorites are deleted with it:

| Action | Favorites it gave (`CanFavorite`) | Favorites it received (`HasFavorites`) |
| --- | --- | --- |
| `$model->delete()` | deleted | deleted |
| `$model->delete()` on a `SoftDeletes` model | kept | kept |
| `$model->restore()` | still there | still there |
| `$model->forceDelete()` | deleted | deleted |

A model with both traits loses both directions. Keeping the favorites on a soft delete means a restored model has exactly the favorites it had.

```php
$post->delete();       // SoftDeletes: the favorites stay
$post->restore();      // $post->favoritedBy($user) is still true
$post->forceDelete();  // the favorites are deleted
```

The cleanup deletes the rows with one query and dispatches no `FavoriteRemoved` events.

### Turning cleanup off

```php title="config/pharaonic/favorites.php"
'cleanup_on_delete' => false,
```

Deleting a model then leaves its favorites in the table. `whereFavoritedBy()` and `withCount()` ignore them for models that no longer exist, but `givenFavorites()` of a favoriter still lists favorites of deleted posts, whose `favoritable` is `null`.

### Deletes that skip the cleanup

The cleanup runs in the model's `deleted` event, so it only happens when a model instance is deleted. These skip it:

- `Post::where(...)->delete()` and other query deletes;
- `DB::table('posts')->delete()` and raw SQL;
- `ON DELETE CASCADE` foreign keys of your own tables;
- `truncate()`.

After such a delete, remove the favorites yourself, for example in the same job:

```php
use Pharaonic\Laravel\Favorites\Models\Favorite;

$ids = Post::where('created_at', '<', now()->subYear())->pluck('id');

Post::whereIn('id', $ids)->delete();
Favorite::where('favoritable_type', (new Post)->getMorphClass())
    ->whereIn('favoritable_id', $ids->map(fn ($id) => (string) $id))
    ->delete();
```

To clean up after the fact, delete the favorites whose model is gone:

```php
Favorite::where('favoritable_type', (new Post)->getMorphClass())
    ->whereNotIn('favoritable_id', Post::withTrashed()->pluck('id')->map(fn ($id) => (string) $id))
    ->delete();
```

Leave out `withTrashed()` for models without `SoftDeletes`. Run it per type, and for `favoriter_type` the same way.
