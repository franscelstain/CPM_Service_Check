<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMPortfolioPerformanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_portfolio_performance', function (Blueprint $table) {
            $table->increments('portfolio_performance_id');
            $table->unsignedInteger('investor_id');
            $table->unsignedInteger('portfolio_risk_id');
            $table->string('portfolio_id')->nullable();
            $table->float('exp_return')->nullable();
            $table->float('exp_risk')->nullable();
            $table->float('sharpe_ratio')->nullable();
            $table->date('portfolio_performance_date');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
            $table->foreign('portfolio_risk_id')->references('portfolio_risk_id')->on('m_portfolio_risk');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_portfolio_performance');
    }
}
