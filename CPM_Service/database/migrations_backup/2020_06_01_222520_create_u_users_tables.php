<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUUsersTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_users', function (Blueprint $table) {
            $table->bigIncrements('user_id');
            $table->unsignedBigInteger('usercategory_id');
            $table->string('fullname');
            $table->string('email', 100)->unique();
            $table->string('password', 100)->nullable();
            $table->enum('is_valid', ['No', 'Yes'])->default('No');
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
        Schema::dropIfExists('u_users');
    }
}
