<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('email_content_id');
            $table->string('title');
            $table->text('text_message')->nullable();
            $table->string('redirect')->nullable();
            $table->json('assign_to');
            $table->string('notif_code');
            $table->boolean('notif_mail')->default(0);
            $table->boolean('notif_web')->default(0);
            $table->boolean('notif_mobile')->default(0);
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('email_content_id')->references('email_content_id')->on('c_email_contents');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_notifications');
    }
}
