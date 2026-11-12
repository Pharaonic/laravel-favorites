## Querying and Counts

Counting and filtering run in SQL. None of them loads the favorites into PHP.

### Counting

```php
$post->favorites()->count();               // select count(*) ...

$posts = Post::withCount('favorites')->get();
$posts->first()->favorites_count;          // 12

$post->loadCount('favorites');
```

:::warning
`$post->favorites->count()` (property, not method) loads every favorite of the post first. Use `favorites()->count()` or `withCount('favorites')`. The package has no `favorites_count` accessor, so it never runs a hidden query.
:::

### Filtering by favoriter

`whereFavoritedBy()` keeps the models the favoriter favorites, through `whereHas()`:

```php
Post::whereFavoritedBy($user)->latest()->paginate();
```

### "Did I favorite this?" in a list

Calling `$user->hasFavorited($post)` for every post of a page runs one query per post. Count the viewer's favorite in the same query instead:

```php
$posts = Post::withCount([
    'favorites',
    'favorites as favorited_by_me' => fn ($query) => $query->whereFavoriter($request->user()),
])->latest()->paginate();

$post->favorites_count;        // 12
(bool) $post->favorited_by_me; // true when the viewer favorited it
```

### Eager loading

Load favorites and both ends with a fixed number of queries:

```php
Post::with('favorites.favoriter')->get();                 // posts, favorites, then one query per favoriter type
$user->givenFavorites()->with('favoritable')->latest()->get();
```

### Indexes

The migration creates:

| Index | Columns | Serves |
| --- | --- | --- |
| `favorites_unique_pair` (unique) | `favoriter_type`, `favoriter_id`, `favoritable_type`, `favoritable_id` | Uniqueness, `hasFavorited()`, `favoritedBy()`, `whereFavoritedBy()`, `givenFavorites()` |
| `favorites_favoritable_index` | `favoritable_type`, `favoritable_id` | `favorites()`, `withCount('favorites')` |

The ids are strings, so the relations compare your model's key as a string too. That keeps both indexes usable, and lets PostgreSQL compare a `bigint` or `uuid` key with the `varchar` column at all.
