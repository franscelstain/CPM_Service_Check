<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMMenusTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_menus', function (Blueprint $table) {
            $table->increments('menu_id');
            $table->unsignedInteger('group_id')->nullable();
            $table->unsignedInteger('usercategory_id');
            $table->integer('parent_id')->nullable();
            $table->string('menu_name', 100);
            $table->enum('menu_type', ['External', 'Internal'])->default('Internal');
            $table->string('slug', 100)->nullable();
            $table->string('icon', 191)->nullable();
            $table->json('button')->nullable();
            $table->integer('sequence_to');
            $table->boolean('published')->nullable();
            $table->boolean('blank_tab')->nullable();
            $table->text('description')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('group_id')->references('group_id')->on('m_menus_groups');
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
        Schema::dropIfExists('m_menus');
    }
}
