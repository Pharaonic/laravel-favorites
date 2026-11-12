<?php

namespace Pharaonic\Laravel\Favorites\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Uses neither trait: a favoriter can still favorite it.
 */
class Product extends Model
{
    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
