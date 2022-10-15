<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMediaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'media',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title')->fulltext();
                $table->string('filename');
                $table->string('image');
                $table->char('hash', 128);
                $table->boolean('is_video');
                $table->smallInteger('duration')->unsigned();
                $table->foreignUuid('album_id')->nullable()->default(null)->constrained('albums', 'id');
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('media');
    }
}
