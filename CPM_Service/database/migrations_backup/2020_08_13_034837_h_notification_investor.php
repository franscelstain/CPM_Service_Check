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
            $table->timestamp('created_at')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            // $table->timestamp();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
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
