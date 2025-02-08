<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUInvestorsPasswordHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors_password_histories', function (Blueprint $table) {
            $table->increments('investor_password_histories_id');
            $table->unsignedInteger('investor_id');
            $table->string('password');
            $table->string('created_by', 50);
            $table->string('created_host', 30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('u_investors_password_histories');
    }
}
