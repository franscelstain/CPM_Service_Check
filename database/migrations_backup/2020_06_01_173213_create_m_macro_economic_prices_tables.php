<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMMacroEconomicPricesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_macro_economic_prices', function (Blueprint $table) {
            $table->bigIncrements('me_price_id');
            $table->unsignedBigInteger('me_category_id');
            $table->date('effective_date');
            $table->string('me_price');
            $table->string('me_type');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('me_category_id')->references('me_category_id')->on('m_macro_economic_categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_macro_economic_prices');
    }
}
