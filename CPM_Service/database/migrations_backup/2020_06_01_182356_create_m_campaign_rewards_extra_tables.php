<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMCampaignRewardsExtraTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_campaign_rewards_extra', function (Blueprint $table) {
            $table->bigIncrements('extra_id');
            $table->unsignedBigInteger('reward_id');
            $table->string('extra_key');
            $table->string('extra_value');
            $table->string('extra_value2');
            $table->enum('extra_type', ['cart', 'point']);
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
        Schema::dropIfExists('m_campaign_rewards_extra');
    }
}
