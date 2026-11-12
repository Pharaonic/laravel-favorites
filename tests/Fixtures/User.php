<?php

namespace Pharaonic\Laravel\Favorites\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Pharaonic\Laravel\Favorites\Concerns\CanFavorite;
use Pharaonic\Laravel\Favorites\Concerns\HasFavorites;

/**
 * Favorites other models and is favorited too.
 */
class User extends Authenticatable
{
    use CanFavorite;
    use HasFavorites;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
