<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTProductsScoresHistoriesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_products_scores_histories', function (Blueprint $table) {
            $table->bigIncrements('product_score_hist_id');
            $table->unsignedBigInteger('product_score_id');
            $table->unsignedInteger('product_id');
            $table->date('score_date');
            $table->float('expected_return_month')->nullable();
            $table->float('expected_return_year');
            $table->float('standard_deviation')->nullable();
            $table->float('sharpe_ratio')->nullable();
            $table->float('treynor_ratio')->nullable();
            $table->float('sortino_ratio')->nullable();
            $table->float('jensen_alpha')->nullable();
            $table->float('capm')->nullable();
            $table->float('roy_safety_ratio')->nullable();
            $table->float('return_year_min')->nullable();
            $table->float('return_year_max')->nullable();
            $table->float('return_month_min')->nullable();
            $table->float('return_month_max')->nullable();
            $table->float('alpha')->nullable();
            $table->float('beta')->nullable();
            $table->float('total_return')->nullable();
            $table->float('annualized_return')->nullable();
            $table->float('geometric_return')->nullable();
            $table->float('apt_return')->nullable();
            $table->float('product_rating')->nullable();
            $table->float('best_year_return')->nullable();
            $table->float('worst_year_return')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_products_scores_histories');
    }
}
