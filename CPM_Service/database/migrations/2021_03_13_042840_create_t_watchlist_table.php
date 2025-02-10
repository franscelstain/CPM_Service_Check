<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTWatchlistTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_watchlist', function (Blueprint $table) {
            $table->bigIncrements('watchlist_id');
            $table->integer('key_id');
            $table->unsignedInteger('usercategory_id');
            $table->unsignedInteger('user_id');
            $table->enum('watchlist_type', ['goal', 'model', 'non_goal', 'product', 'retirement']);
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('usercategory_id')->references('usercategory_id')->on('u_users_categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_watchlist');
    }
}
