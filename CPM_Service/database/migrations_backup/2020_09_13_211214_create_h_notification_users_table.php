<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHNotificationUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('h_notification_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('notif_title')->nullable();
            $table->text('notif_desc')->nullable();
            $table->boolean('notif_status')->default(0);
            $table->boolean('notif_status_batch')->default(0);
            $table->text('notif_href')->nullable();
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->integer('users_on')->nullable();  
            // $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('h_notification_users');
    }
}
