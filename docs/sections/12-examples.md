## Examples

### 1. A favorite button

Send the state the user wants, so a double click can't undo itself, and return the count for the button.

- ===Routes

  ```php title="routes/web.php"
  use App\Http\Controllers\PostFavoriteController;

  Route::middleware('auth')->group(function () {
      Route::post('posts/{post}/favorite', [PostFavoriteController::class, 'store']);
      Route::delete('posts/{post}/favorite', [PostFavoriteController::class, 'destroy']);
  });
  ```

- ===Controller

  ```php title="app/Http/Controllers/PostFavoriteController.php"
  namespace App\Http\Controllers;

  use App\Models\Post;
  use Illuminate\Http\Request;

  class PostFavoriteController extends Controller
  {
      public function store(Request $request, Post $post)
      {
          $request->user()->favorite($post);

          return ['favorited' => true, 'favorites' => $post->favorites()->count()];
      }

      public function destroy(Request $request, Post $post)
      {
          $request->user()->unfavorite($post);

          return ['favorited' => false, 'favorites' => $post->favorites()->count()];
      }
  }
  ```

### 2. A feed with counts and the viewer's favorites

One query for the posts, their counts and whether the viewer favorited each one.

```php title="app/Http/Controllers/FeedController.php"
public function index(Request $request)
{
    $posts = Post::withCount([
        'favorites',
        'favorites as favorited_by_me' => fn ($query) => $query->whereFavoriter($request->user()),
    ])->latest()->paginate(20);

    return view('feed', compact('posts'));
}
```

### 3. A "favorited posts" page

```php
$posts = Post::whereFavoritedBy($request->user())->latest()->paginate(20);
```

Or newest favorite first, with every type the user favorited:

```php
$favorites = $request->user()->givenFavorites()->with('favoritable')->latest()->paginate(20);
```

### 4. Users who favorite each other

`User` uses both traits, so users can favorite users.

```php title="app/Models/User.php"
class User extends Authenticatable
{
    use CanFavorite, HasFavorites;
}
```

```php
$alice->favorite($bob);

$bob->favorites()->with('favoriter')->get();      // who favorited Bob
$alice->givenFavorites()->count();            // how many favorites Alice gave
User::whereFavoritedBy($alice)->get();        // the users Alice favorites
```

### 5. Notifying the author

```php title="app/Listeners/NotifyAuthor.php"
public function handle(FavoriteCreated $event)
{
    if ($event->favoritable instanceof Post && ! $event->favoritable->author->is($event->favoriter)) {
        $event->favoritable->author->notify(new PostFavorited($event->favoriter));
    }
}
```

Laravel discovers it from the `FavoriteCreated` type of `handle()`; see [Events](#events) to register it yourself.
