<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class HNotificationInvestor extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('h_notification_investor', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('investor_id');
            $table->text('notif_title')->nullable();
            $table->text('notif_desc')->nullable();
            $table->boolean('notif_status')->default(0);
            $table->boolean('notif_status_batch')->default(0);
            $table->text('notif_href')->nullable();
            $table->timestamps('created_at')->nullable();
            $table->foreign('investor_id')->references('investor_id')->on('u_investor');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('h_notification_investor');
    }
}
