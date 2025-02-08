<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCApiTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('c_api', function (Blueprint $table) {
            $table->increments('api_id');
            $table->string('api_name');
            $table->string('slug');
            $table->string('data_key', 50);
            $table->enum('content_type', ['json', 'xml'])->default('json');
            $table->enum('get_token', ['No', 'Yes'])->default('No');
            $table->string('authorization')->nullable();
            $table->string('user_label')->nullable();
            $table->string('username')->nullable();
            $table->string('pass_label')->nullable();
            $table->string('password')->nullable();
            $table->enum('auth_method', ['GET', 'POST'])->nullable()->default('POST');
            $table->string('auth_link')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
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
        Schema::dropIfExists('c_api');
    }
}
