## Relationships

All relations are native Eloquent relations, so everything Eloquent offers works on them.

| Relation | On | Type | Holds |
| --- | --- | --- | --- |
| `givenFavorites()` | `CanFavorite` models | `MorphMany` of `Favorite` | The favorites the model gave. |
| `favorites()` | `HasFavorites` models | `MorphMany` of `Favorite` | The favorites the model received. |
| `favoriter()` | `Favorite` | `MorphTo` | The model that gave the favorite. |
| `favoritable()` | `Favorite` | `MorphTo` | The model that received the favorite. |

### Why `givenFavorites()` and not `favorites()`

The favoriter side's relation is called `givenFavorites()` so it never clashes with `favorites()` on the favorited side. A `User` can use both traits without a trait conflict, and `$user->favorites` always means the favorites the user received.

### Reading through the relations

```php
use Pharaonic\Laravel\Favorites\Models\Favorite;

$favorite = $post->favorites()->latest()->first();

$favorite->favoriter;    // App\User
$favorite->favoritable; // App\Post
$favorite->created_at;

$user->givenFavorites()->with('favoritable')->latest()->get()
    ->map(fn (Favorite $favorite) => $favorite->favoritable); // the posts, comments, ... the user favorited
```

### The `Favorite` model

`Pharaonic\Laravel\Favorites\Models\Favorite` reads the table from `pharaonic.favorites.table` and has standard timestamps. It has two scopes for filtering favorites by one end:

```php
Favorite::whereFavoriter($user)->count();      // favorites the user gave
Favorite::whereFavoritable($post)->count();   // favorites the post received

User::whereHas('givenFavorites', fn ($query) => $query->whereFavoritable($post))->get(); // who favorited the post
```

The favorites table lives on the default database connection. Counts, filters and eager loading need it in the same database as your models.
