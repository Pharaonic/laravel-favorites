## Installation

Install the package with Composer and create its table.

### Requirements

- PHP 8.0 or 8.1
- Laravel 8
- MySQL, MariaDB, PostgreSQL or SQLite

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require pharaonic/laravel-favorites
```

The service provider is registered automatically.

### Run Migrations

The package loads its migration automatically, so the `favorites` table is created with your other migrations:

```bash title="Terminal" no-line-numbers
php artisan migrate
```

To change the migration before it runs, publish it into `database/migrations`:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=favorites-migrations
```

The published file keeps the package's file name, `2026_11_12_000001_create_pharaonic_favorites_table.php`, so Laravel runs your copy instead of the package's, never both.

:::warning Existing favorites table
The migration stops with an error when a `favorites` table already exists, for example one that another package or an earlier migration created. Set `pharaonic.favorites.table` to another name before migrating, or move that table out of the way first.
:::

### Publish Configuration

Publish the configuration only when you need another table name or want to turn off cleanup on delete:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=favorites-config
```

The file is published to `config/pharaonic/favorites.php`.

:::success Installation Complete
You're all set! Add `CanFavorite` and `HasFavorites` to your models, as [Basic Usage](#basic-usage) shows.
:::
