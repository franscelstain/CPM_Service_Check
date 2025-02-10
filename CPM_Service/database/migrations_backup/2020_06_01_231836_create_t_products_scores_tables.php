<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTProductsScoresTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_products_scores', function (Blueprint $table) {
            $table->bigIncrements('product_score_id');
            $table->unsignedBigInteger('product_id');
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
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
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
        Schema::dropIfExists('t_products_scores');
    }
}
