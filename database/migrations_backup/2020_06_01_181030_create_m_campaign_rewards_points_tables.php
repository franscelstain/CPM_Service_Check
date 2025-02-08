<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMCampaignRewardsPointsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_campaign_rewards_points', function (Blueprint $table) {
            $table->bigIncrements('point_id');
            $table->unsignedBigInteger('point_action_id');
            $table->unsignedBigInteger('expired_point_id');
            $table->string('point_name', 100);
            $table->date('effective_date');
            $table->date('point_date_from')->nullable();
            $table->date('point_date_to')->nullable();
            $table->integer('point');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('point_action_id')->references('campaign_ref_id')->on('m_campaign_references');
            $table->foreign('expired_point_id')->references('campaign_ref_id')->on('m_campaign_references');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_campaign_rewards_points');
    }
}
