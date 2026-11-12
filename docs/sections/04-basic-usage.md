## Basic Usage

Add `CanFavorite` to the model that favorites and `HasFavorites` to the model that is favorited.

```php title="app/Models/User.php"
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Favorites\Concerns\CanFavorite;

class User extends Authenticatable
{
    use CanFavorite;
}
```

```php title="app/Models/Post.php"
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Favorites\Concerns\HasFavorites;

class Post extends Model
{
    use HasFavorites;
}
```

The favoriter doesn't have to be a user, and nothing in the package reads the authenticated user: a `Team`, a `Bot` or any other model can use `CanFavorite`. A model can be favorited without `HasFavorites` too; the trait adds the `favorites()` relation and the `favoriteBy()`, `unfavoriteBy()`, `favoritedBy()` and `whereFavoritedBy()` helpers.

### Favoriting and unfavoriting

`favorite()` and `unfavorite()` return whether **this call** changed the table, not the current state:

```php
$user->favorite($post);     // true: the favorite was created
$user->favorite($post);     // false: it already existed, nothing changed

$user->unfavorite($post);   // true: the favorite was removed
$user->unfavorite($post);   // false: there was no favorite, nothing changed

$user->hasFavorited($post); // false
```

Both are idempotent: calling them again leaves the same state. Use `hasFavorited()` when you need the current state.

### Toggling

`toggleFavorite()` returns the **new state**, true when the pair is favorited after the call:

```php
$user->toggleFavorite($post); // true: now favorited
$user->toggleFavorite($post); // false: now not favorited
```

A toggle is not idempotent: two calls cancel each other. When two toggles of the same pair arrive at the same moment, the result is not guaranteed to be what two toggles in a row would give (see [Integrity and Concurrency](#integrity-and-concurrency)). Prefer `favorite()` and `unfavorite()` for endpoints that a user can click twice.

### From the favorited model

`HasFavorites` adds the same operations seen from the other side. They work on the same row as the `CanFavorite` methods:

```php
$post->favoriteBy($user);    // true when created, like $user->favorite($post)
$post->favoritedBy($user);   // true, like $user->hasFavorited($post)
$post->unfavoriteBy($user);  // true when removed, like $user->unfavorite($post)

$user->favorite($post);
$post->unfavoriteBy($user);  // true
$user->hasFavorited($post);  // false
```

### A model that favorites and is favorited

A model can use both traits. `givenFavorites()` holds the favorites it gave and `favorites()` the favorites it received:

```php
class User extends Authenticatable
{
    use CanFavorite, HasFavorites;
}

$alice->favorite($bob);

$alice->givenFavorites()->count(); // 1
$alice->favorites()->count();      // 0
$bob->favorites()->count();        // 1
$bob->favoritedBy($alice);         // true
```

### Unsaved models

The writes need both models saved, because a favorite stores their keys. `favorite()`, `unfavorite()`, `toggleFavorite()`, `favoriteBy()` and `unfavoriteBy()` throw an `InvalidArgumentException` before touching the database when either model is unsaved. The reads `hasFavorited()` and `favoritedBy()` return false, and `whereFavoritedBy()` matches nothing.

```php
$user->favorite(new Post); // InvalidArgumentException: The favoritable [App\Models\Post] is not saved: ...
$user->hasFavorited(new Post); // false
```
