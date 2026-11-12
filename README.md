<p align="center"><a href="https://pharaonic.dev/packages/laravel/favorites" target="_blank"><img src="https://raw.githubusercontent.com/Pharaonic/laravel-favorites/develop/docs/cover.webp"></a></p>

<p align="center">
  <a href="https://php.net" target="_blank"><img src="https://img.shields.io/static/v1?label=PHP&message=8.1%20|%208.2%20|%208.3&color=blue&style=flat-square" alt="PHP Version : 8.1 | 8.2 | 8.3"></a>
  <a href="https://laravel.com" target="_blank"><img src="https://img.shields.io/static/v1?label=Laravel&message=10.x&color=F05340&style=flat-square" alt="Laravel Version : 10.x"></a>
  <img src="https://img.shields.io/static/v1?label=License&message=MIT&color=brightgreen&style=flat-square" alt="License">
  <img src="https://github.com/Pharaonic/laravel-favorites/actions/workflows/build.yml/badge.svg?branch=10.x" alt="Build">
  <br>
  <a href="https://packagist.org/packages/Pharaonic/laravel-favorites" target="_blank"><img src="https://img.shields.io/static/v1?label=Packagist&message=pharaonic/laravel-favorites&color=blue&logo=packagist&logoColor=white" alt="Source"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-favorites" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-favorites/v" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-favorites" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-favorites/downloads" alt="Packagist Downloads"></a>
</p>

<h3 align="center">Polymorphic favorites for Eloquent models, kept unique by the database.</h3>
<br>

## Installation

```bash
composer require pharaonic/laravel-favorites
php artisan migrate
```

The service provider is registered automatically, and `migrate` creates the `favorites` table. Publish the migration only to change it (`--tag=favorites-migrations`), and the configuration only to rename the table or turn off cleanup on delete:

```bash
php artisan vendor:publish --tag=favorites-config
```

## Usage

Add `CanFavorite` to the model that favorites (it does not have to be a user) and `HasFavorites` to the model that is favorited. A model can use both.

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Favorites\Concerns\CanFavorite;

class User extends Authenticatable
{
    use CanFavorite;
}
```

```php
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Favorites\Concerns\HasFavorites;

class Post extends Model
{
    use HasFavorites;
}
```

```php
$user->favorite($post);       // true: this call created the favorite
$user->favorite($post);       // false: it already existed
$user->hasFavorited($post);   // true
$user->unfavorite($post);     // true: this call removed it, false when there was none
$user->toggleFavorite($post); // the new state: true when favorited

$post->favoriteBy($user);     // same as $user->favorite($post)
$post->unfavoriteBy($user);   // same as $user->unfavorite($post)
$post->favoritedBy($user);    // same as $user->hasFavorited($post)

Post::withCount('favorites')->with('favorites.favoriter')->get();
Post::whereFavoritedBy($user)->latest()->paginate();
$user->givenFavorites()->with('favoritable')->latest()->get();
```

## Documentation

Full documentation: [Laravel Favorites Documentation](https://pharaonic.dev/packages/laravel/favorites).

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md). Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE](LICENSE).
