<?php

namespace Pharaonic\Laravel\Favorites\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Favorites\Concerns\CanFavorite;
use Pharaonic\Laravel\Favorites\Concerns\HasFavorites;

/**
 * A string primary key chosen by the application, such as "000123".
 */
class Sku extends Model
{
    use CanFavorite;
    use HasFavorites;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
