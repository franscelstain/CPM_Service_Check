<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMCampaignReferencesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_campaign_references', function (Blueprint $table) {
            $table->bigIncrements('campaign_ref_id');
            $table->string('campaign_ref_name');
            $table->enum('campaign_ref_type', ['cart-action', 'cart-item', 'customer-group', 'expired-point', 'investor', 'point-action', 'product']);
            $table->enum('is_attribute', ['No', 'Yes'])->default('No');
            $table->text('description')->nullable();
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
        Schema::dropIfExists('m_campaign_references');
    }
}
