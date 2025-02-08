<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTWatchlistInvestorsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_watchlist_investors', function (Blueprint $table) {
            $table->bigIncrements('watchlist_investor_id');
            $table->unsignedBigInteger('key_id');
            $table->unsignedBigInteger('investor_id')->nullable();
            $table->enum('watchlist_type', ['product', 'goal', 'model']);
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_watchlist_investors');
    }
}
