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
            $table->increments('fee_product_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('fee_id');
            $table->unsignedInteger('fee_type_id');
            $table->float('fee_value');
            $table->date('effective_date');
            $table->enum('value_type', ['Credit', 'Percentage'])->default('Percentage');
            $table->enum('is_expired', ['No', 'Yes'])->default('No');
            $table->text('description')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('product_id')->references('product_id')->on('m_products');
            $table->foreign('fee_id')->references('fee_reference_id')->on('m_fee_reference');
            $table->foreign('fee_type_id')->references('fee_reference_id')->on('m_fee_reference');
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
