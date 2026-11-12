<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\Favorites\Models\Favorite;

/**
 * One row per favoriter and favoritable pair. The ids are strings, so integer,
 * UUID and ULID keys can share the table, and the unique index is what
 * keeps a pair from being favorited twice, even under concurrent requests.
 */
class CreatePharaonicFavoritesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $name = (new Favorite)->getTable();

        if (Schema::hasTable($name)) {
            throw new RuntimeException(sprintf(
                'The [%s] table already exists, so pharaonic/laravel-favorites cannot create it. '
                .'Set pharaonic.favorites.table to another name, or move the existing table out of the way.',
                $name
            ));
        }

        Schema::create($name, function (Blueprint $table) use ($name) {
            $table->bigIncrements('id');
            $table->string('favoriter_type', 191);
            $table->string('favoriter_id', 64);
            $table->string('favoritable_type', 191);
            $table->string('favoritable_id', 64);
            $table->timestamps();

            // Its first two columns also serve "the favorites this model gave".
            $table->unique(['favoriter_type', 'favoriter_id', 'favoritable_type', 'favoritable_id'], $name.'_unique_pair');
            $table->index(['favoritable_type', 'favoritable_id'], $name.'_favoritable_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists((new Favorite)->getTable());
    }
}
