## Configuration

Both settings are optional. Publish the file with `php artisan vendor:publish --tag=favorites-config` to change them.

```php title="config/pharaonic/favorites.php"
return [
    'table' => 'favorites',
    'cleanup_on_delete' => true,
];
```

| Key | Default | Description |
| --- | --- | --- |
| `pharaonic.favorites.table` | `favorites` | The table the migration creates and the `Favorite` model uses. |
| `pharaonic.favorites.cleanup_on_delete` | `true` | Delete a model's favorites when the model is deleted. See [Deletes and Soft Deletes](#deletion-and-soft-deletes). |

### Changing the table name

The migration reads `pharaonic.favorites.table` when it runs, so set the name **before** the first `php artisan migrate`:

```php title="config/pharaonic/favorites.php"
'table' => 'post_favorites',
```

Index names follow the table name (`post_favorites_unique_pair`, `post_favorites_favoritable_index`).

:::warning
Changing the setting after the migration ran does not rename the table: the package then reads and writes a table that doesn't exist. Rename the table in a migration of your own (`Schema::rename('favorites', 'post_favorites')`) at the same time as you change the setting. Rolling back the package migration drops the table the setting names at that moment.
:::
