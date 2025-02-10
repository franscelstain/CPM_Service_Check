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
            $table->increments('user_id');
            $table->unsignedInteger('usercategory_id');
            $table->unsignedInteger('leader_id')->nullable();
            $table->string('user_code', 50)->nullable();
            $table->string('fullname');
            $table->string('photo_profile')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('mobile_phone', 20)->nullable();
            $table->string('email', 100);
            $table->string('password', 100)->nullable();
            $table->string('ext_code', 50)->nullable();
            $table->enum('is_enable', ['No', 'Yes'])->default('Yes');            
            $table->enum('is_data', ['APPS', 'WS'])->default('APPS');
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
