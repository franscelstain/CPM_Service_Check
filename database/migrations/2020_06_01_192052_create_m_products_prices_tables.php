<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMProductsPricesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_products_prices', function (Blueprint $table) {
            $table->increments('price_id');
            $table->unsignedInteger('product_id');
            $table->date('price_date');
            $table->float('price_value');
            $table->float('aum')->nullable();
            $table->float('open_price')->nullable();
            $table->float('closed_rice')->nullable();
            $table->float('adj_closed_price')->nullable();
            $table->float('high_price')->nullable();
            $table->float('low_price')->nullable();
            $table->float('bid')->nullable();
            $table->float('volume')->nullable();
            $table->float('predictive_price');
            $table->string('fund_unit');
            $table->float('managed_funds')->nullable();
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
        Schema::dropIfExists('m_products_prices');
    }
}
