<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMCampaignRewardsCartsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_campaign_rewards_carts', function (Blueprint $table) {
            $table->bigIncrements('cart_id');
            $table->unsignedBigInteger('cart_action_id');
            $table->string('cart_name', 100);
            $table->date('cart_date_from');
            $table->date('cart_date_to');
            $table->enum('cart_type', ['Flat', 'Tiering'])->default('Flat');
            $table->enum('coupon', ['No Coupon', 'Specific Coupon'])->default('No Coupon');
            $table->string('coupon_code')->nullable();
            $table->string('uses_per_coupon')->nullable();
            $table->string('uses_per_customer')->nullable();
            $table->integer('amount');
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->text('description')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('cart_action_id')->references('campaign_ref_id')->on('m_campaign_references');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_campaign_rewards_carts');
    }
}
