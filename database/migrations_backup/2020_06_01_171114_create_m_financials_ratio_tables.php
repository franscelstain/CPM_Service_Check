<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMFinancialsRatioTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_financials_ratio', function (Blueprint $table) {
            $table->bigIncrements('ratio_id');
            $table->string('ratio_name');
            $table->date('effective_date');
            $table->enum('ratio_type', ['Nominal', 'Percent'])->default('Percent');
            $table->string('ratio_method')->nullable();
            $table->float('perfect_value');
            $table->string('perfect_operator');
            $table->float('bad_value');
            $table->string('bad_operator');
            $table->float('warning_value');
            $table->float('warning_value2')->nullable();
            $table->string('warning_operator');
            $table->enum('published', ['No', 'Yes'])->default('Yes');
            $table->integer('sequence_to');
            $table->text('description')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_financials_ratio');
    }
}
