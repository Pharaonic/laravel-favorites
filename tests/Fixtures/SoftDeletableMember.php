<?php

namespace Pharaonic\Laravel\Favorites\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pharaonic\Laravel\Favorites\Concerns\CanFavorite;

class SoftDeletableMember extends Model
{
    use CanFavorite;
    use SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
