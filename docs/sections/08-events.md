## Events

The package dispatches two events, only when a call actually changed the table:

| Event | Dispatched by | Not dispatched |
| --- | --- | --- |
| `Pharaonic\Laravel\Favorites\Events\FavoriteCreated` | `favorite()`, `favoriteBy()`, `toggleFavorite()` when they insert the favorite | When the favorite already existed |
| `Pharaonic\Laravel\Favorites\Events\FavoriteRemoved` | `unfavorite()`, `unfavoriteBy()`, `toggleFavorite()` when they delete the favorite | When there was no favorite, and for the cleanup on delete |

Both have three public properties: `$favorite` (the `Favorite` row), `$favoriter`, and `$favoritable`. In `FavoriteRemoved`, `$favorite` is the deleted row, with `exists` false.

```php title="app/Listeners/NotifyAuthor.php"
namespace App\Listeners;

use App\Notifications\PostFavorited;
use Pharaonic\Laravel\Favorites\Events\FavoriteCreated;

class NotifyAuthor
{
    public function handle(FavoriteCreated $event)
    {
        if ($event->favoritable instanceof \App\Post) {
            $event->favoritable->author->notify(new PostFavorited($event->favoriter));
        }
    }
}
```

```php title="app/Providers/EventServiceProvider.php"
protected $listen = [
    \Pharaonic\Laravel\Favorites\Events\FavoriteCreated::class => [
        \App\Listeners\NotifyAuthor::class,
    ],
];
```

### When listeners run

The events are dispatched right after the insert or delete. When you favorite inside a database transaction, the listeners run before that transaction commits, and a rollback afterwards does not take the event back. Make a listener that must only see committed favorites a queued listener dispatched after your transaction, or call `favorite()` outside it.

### Model events

`FavoriteCreated` comes with the `Favorite` model's own `creating` and `created` events, because the favorite is saved through Eloquent; a `creating` listener that returns false cancels the favorite, and `favorite()` then returns false. The removal deletes the row with a query, so the `Favorite` model's `deleting` and `deleted` events do not fire. Listen to `FavoriteRemoved` instead.
