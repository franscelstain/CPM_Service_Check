<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMProductsFeeTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_products_fee', function (Blueprint $table) {
            $table->bigIncrements('fee_product_id');
            $table->unsignedBigInteger('product_id');
            $table->float('fee_percentage');
            $table->date('effective_date');
            $table->enum('is_expired', ['No', 'Yes'])->default('No');
            $table->text('description')->nullable();
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
        Schema::dropIfExists('m_products_fee');
    }
}
