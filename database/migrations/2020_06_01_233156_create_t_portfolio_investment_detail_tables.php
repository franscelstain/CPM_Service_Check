<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTPortfolioInvestmentDetailTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_portfolio_investment_detail', function (Blueprint $table) {
            $table->bigIncrements('investment_detail_id');
            $table->unsignedBigInteger('investment_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('fee_product_id')->nullable();
            $table->unsignedInteger('investor_account_id')->nullable();
            $table->integer('debt_date')->nullable();
            $table->float('amount')->nullable();
            $table->float('net_amount');
            $table->float('fee_amount')->nullable();
            $table->float('tax_amount')->nullable();
            $table->float('expected_return_year')->nullable();
            $table->float('expected_return_month')->nullable();
            $table->float('target_allocation')->nullable();
            $table->float('sharpe_ratio')->nullable();
            $table->float('volatility')->nullable();
            $table->enum('investment_type', ['Lumpsum', 'SIP'])->default('Lumpsum');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investment_id')->references('investment_id')->on('t_portfolio_investment');
            $table->foreign('product_id')->references('product_id')->on('m_products');
            $table->foreign('fee_product_id')->references('fee_product_id')->on('m_products_fee');
            $table->foreign('investor_account_id')->references('investor_account_id')->on('u_investors_accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_portfolio_investment_detail');
    }
}
