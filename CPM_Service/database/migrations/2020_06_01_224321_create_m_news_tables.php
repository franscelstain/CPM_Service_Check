<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMNewsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_news', function (Blueprint $table) {
            $table->increments('news_id');
            $table->unsignedInteger('author_id');
            $table->string('news_title');
            $table->string('news_slug');
            $table->string('news_image')->nullable();
            $table->text('news_content');
            $table->enum('published', ['No', 'Yes'])->default('No');
            $table->date('published_date');
            $table->enum('published_to', ['Internal', 'Public'])->default('Public');
            $table->integer('visited')->nullable();
            $table->enum('is_promo', ['No', 'Yes'])->default('No');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('author_id')->references('user_id')->on('u_users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_news');
    }
}
