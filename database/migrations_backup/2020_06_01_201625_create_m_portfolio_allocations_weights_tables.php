<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMPortfolioAllocationsWeightsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_portfolio_allocations_weights', function (Blueprint $table) {
            $table->bigIncrements('allocation_weight_id');
            $table->unsignedBigInteger('model_id');
            $table->date('effective_date');
            $table->float('expected_return_month')->nullable();
            $table->float('expected_return_year');
            $table->float('volatility')->nullable();
            $table->float('sharpe_ratio')->nullable();
            $table->float('treynor_ratio')->nullable();
            $table->float('sortino_ratio')->nullable();
            $table->float('jensen_alpha')->nullable();
            $table->float('capm')->nullable();
            $table->float('roy_safety_ratio')->nullable();
            $table->float('aum')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('model_id')->references('model_id')->on('m_models');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_portfolio_allocations_weights');
    }
}
