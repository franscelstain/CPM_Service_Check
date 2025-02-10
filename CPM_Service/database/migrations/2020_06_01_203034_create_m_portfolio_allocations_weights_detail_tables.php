<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMPortfolioAllocationsWeightsDetailTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_portfolio_allocations_weights_detail', function (Blueprint $table) {
            $table->increments('allocation_weight_detail_id');
            $table->unsignedInteger('allocation_weight_id');
            $table->unsignedInteger('product_id');
            $table->float('weight');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('allocation_weight_id', 'm_portfolio_allocation_weight_dtl_allocation_weight_id_foreign')->references('allocation_weight_id')->on('m_portfolio_allocations_weights');
            $table->foreign('product_id')->references('product_id')->on('m_products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_portfolio_allocations_weights_detail');
    }
}
