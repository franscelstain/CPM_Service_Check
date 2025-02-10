<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMNotificationInvestorIntervalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_notification_investor_interval', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('investor_notif_id');
            $table->string('reminder', 10);
            $table->integer('count_reminder')->nullable();
            $table->boolean('continuous')->default(0);
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_notif_id')->references('id')->on('m_notification_investor');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_notification_investor_interval');
    }
}
