<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMProductsBondsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
         Schema::create('m_products_bonds', function (Blueprint $table) {
            $table->increments('bond_id');
            $table->unsignedInteger('product_id');
            $table->string('bonds_type', 20);
            $table->string('rating', 20);
            $table->float('coupon_value')->nullable();
            $table->boolean('second_market');
            $table->string('coupon_type', 50);
            $table->string('coupon_payment', 50);
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
        Schema::dropIfExists('m_products_bonds');
    }
}
