<?php

namespace Pharaonic\Laravel\Favorites\Events;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Favorites\Models\Favorite;

/**
 * Dispatched once for each favorite this package inserts, right after the insert.
 */
final class FavoriteCreated
{
    public function __construct(
        public Favorite $favorite,
        public Model $favoriter,
        public Model $favoritable,
    ) {
    }
}
