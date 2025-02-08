<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTPortfolioInvestmentTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_portfolio_investment', function (Blueprint $table) {
            $table->bigIncrements('investment_id');
            $table->unsignedInteger('investor_id');
            $table->unsignedInteger('goal_id')->nullable();
            $table->unsignedInteger('profile_id');
            $table->unsignedInteger('model_id');
            $table->unsignedInteger('status_id')->nullable();
            $table->string('portfolio_id', 50);
            $table->date('investment_date');
            $table->string('investment_name');
            $table->enum('investment_category', ['goal', 'non_goal', 'portfolio'])->default('goal');
            $table->float('today_amount');
            $table->integer('time_horizon');
            $table->float('investment_amount');
            $table->float('projected_amount');
            $table->float('total_return');
            $table->float('future_amount');
            $table->float('first_investment');
            $table->float('monthly_investment')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
            $table->foreign('goal_id')->references('goal_id')->on('m_goals');
            $table->foreign('profile_id')->references('profile_id')->on('m_risk_profiles');
            $table->foreign('model_id')->references('model_id')->on('m_models');
            $table->foreign('status_id')->references('trans_reference_id')->on('m_trans_reference');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_portfolio_investment');
    }
}
