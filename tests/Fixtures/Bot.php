<?php

namespace Pharaonic\Laravel\Favorites\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Favorites\Concerns\CanFavorite;

/**
 * A favoriter that is not a user.
 */
class Bot extends Model
{
    use CanFavorite;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
