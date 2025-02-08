<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMNotificationInvestorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_notification_investor', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('email_content_id');
            $table->string('title');
            $table->text('text_message')->nullable();
            $table->string('redirect')->nullable();
            $table->boolean('notif_mail')->default(0);
            $table->boolean('notif_api')->default(0);
            $table->boolean('notif_web')->default(0);
            $table->boolean('notif_mobile')->default(0);
            $table->text('email_content_text');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('category_id')->references('id')->on('m_notification_categories');
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
        Schema::dropIfExists('m_notification_investor');
    }
}
