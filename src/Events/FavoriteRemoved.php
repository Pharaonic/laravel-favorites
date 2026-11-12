<?php

namespace Pharaonic\Laravel\Favorites\Events;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Favorites\Models\Favorite;

/**
 * Dispatched once for each favorite this package deletes through unfavorite(),
 * unfavoriteBy() or toggleFavorite(), right after the delete. $favorite is the deleted row.
 */
final class FavoriteRemoved
{
    public function __construct(
        public Favorite $favorite,
        public Model $favoriter,
        public Model $favoritable,
    ) {}
}
