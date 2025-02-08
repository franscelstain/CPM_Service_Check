<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCEmailContentsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('c_email_contents', function (Blueprint $table) {
            $table->bigIncrements('email_content_id');
            $table->unsignedBigInteger('layout_id');
            $table->string('email_content_name', 50);
            $table->text('email_content_text');
            $table->text('email_subject');
            $table->json('email_change')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('layout_id')->references('layout_id')->on('c_email_layouts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('c_email_contents');
    }
}
