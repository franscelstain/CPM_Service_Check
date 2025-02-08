<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTGoalInvestmentTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_goal_investment', function (Blueprint $table) {
            $table->bigIncrements('goal_invest_id');
            $table->unsignedBigInteger('investor_id');
            $table->unsignedBigInteger('goal_id');
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('sales_id')->nullable();
            $table->date('goal_invest_date');
            $table->string('goal_invest_name');
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
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_goal_investment');
    }
}
