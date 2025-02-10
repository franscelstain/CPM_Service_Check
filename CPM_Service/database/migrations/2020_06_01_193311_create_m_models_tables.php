<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMModelsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_models', function (Blueprint $table) {
            $table->increments('model_id');
            $table->unsignedInteger('benchmark');
            $table->unsignedInteger('benchmark2')->nullable();
            $table->string('model_name', 50);
            $table->text('description')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('benchmark')->references('product_id')->on('m_products');
            $table->foreign('benchmark2')->references('product_id')->on('m_products');
            $table->enum('is_approve', ['No', 'Yes'])->default('No');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_models');
    }
}
