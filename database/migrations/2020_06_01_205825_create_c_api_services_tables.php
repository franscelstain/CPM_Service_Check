<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCApiServicesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('c_api_services', function (Blueprint $table) {
            $table->increments('service_id');
            $table->unsignedInteger('api_id');
            $table->string('service_name', 50);
            $table->string('service_path')->nullable();
            $table->enum('method', ['DELETE', 'GET', 'PATCH', 'POST', 'PUT'])->default('POST');
            $table->text('description')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('api_id')->references('api_id')->on('c_api');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('c_api_services');
    }
}
