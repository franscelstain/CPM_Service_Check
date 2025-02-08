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
            $table->unsignedBigInteger('goal_id');
            $table->string('goal_name');
            $table->unsignedBigInteger('profile_id');
            $table->string('profile_name');
            $table->unsignedBigInteger('model_id');
            $table->string('model_name');
            $table->bigInteger('investor_id');
            $table->string('investor_name');
            $table->bigInteger('sales_id');
            $table->string('sales_name');
            $table->date('goal_invest_date');
            $table->string('title');
            $table->float('today_amount');
            $table->integer('time_horizon');
            $table->float('investment');
            $table->float('investment_amount');
            $table->float('future_amount');
            $table->float('total_return');
            $table->float('projected_amount');
            $table->float('total_amount');
            $table->float('total_fee');
            $table->float('total_payment');
            $table->enum('is_confirm', ['No', 'Yes'])->default('No');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
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
