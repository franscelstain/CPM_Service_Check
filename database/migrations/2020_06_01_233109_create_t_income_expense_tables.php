<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTIncomeExpenseTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_income_expense', function (Blueprint $table) {
            $table->bigIncrements('transaction_id');
            $table->unsignedInteger('investor_id');
            $table->unsignedInteger('financial_id');
            $table->string('transaction_name');
            $table->enum('period_of_time', ['Monthly', 'Yearly'])->default('Monthly');
            $table->float('amount');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('financial_id')->references('financial_id')->on('m_financials');
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_income_expense');
    }
}
