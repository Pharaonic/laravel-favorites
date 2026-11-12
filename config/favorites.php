<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Favorites table
    |--------------------------------------------------------------------------
    |
    | The table that stores every favorite. The package migration creates it with
    | this name, and the Favorite model reads and writes it. Changing it after the
    | migration ran does not rename the existing table.
    |
    */

    'table' => 'favorites',

    /*
    |--------------------------------------------------------------------------
    | Clean up on delete
    |--------------------------------------------------------------------------
    |
    | When a model that uses CanFavorite or HasFavorites is deleted (or force deleted,
    | when it uses SoftDeletes), delete the favorites it gave or received. A soft
    | delete always keeps them, so a restored model keeps its favorites.
    |
    */

    'cleanup_on_delete' => true,

];
