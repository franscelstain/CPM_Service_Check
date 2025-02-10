<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTGoalInvestmentDetailTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_goal_investment_detail', function (Blueprint $table) {
            $table->bigIncrements('goal_invest_detail_id');
            $table->unsignedBigInteger('goal_invest_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->unsignedBigInteger('asset_class_id');
            $table->string('asset_class_name');
            $table->string('investment_type');
            $table->date('billing_date')->nullable();
            $table->bigInteger('payment_method_id');
            $table->float('amount');
            $table->float('expected_return');
            $table->float('allocation');
            $table->float('sharpe_ratio');
            $table->float('treynor_ratio');
            $table->unsignedBigInteger('fee_product_id');
            $table->float('fee_percentage');
            $table->float('fee_product');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('goal_invest_id')->references('goal_invest_id')->on('t_goal_investment');
            $table->foreign('product_id')->references('product_id')->on('m_products');
            $table->foreign('asset_class_id')->references('asset_class_id')->on('m_asset_class');
            $table->foreign('fee_product_id')->references('fee_product_id')->on('m_products_fee');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_goal_investment_detail');
    }
}
