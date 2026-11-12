<?php

namespace Pharaonic\Laravel\Favorites\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Favorites\Concerns\HasFavorites;

class Comment extends Model
{
    use HasFavorites;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
