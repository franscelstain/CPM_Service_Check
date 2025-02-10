<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMCovarianTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_covarian', function (Blueprint $table) {
            $table->increments('covarian_id');
            $table->unsignedInteger('product_id_1');
            $table->unsignedInteger('product_id_2');
            $table->date('covarian_date');
            $table->float('covarian_value');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('product_id_1')->references('product_id')->on('m_products');
            $table->foreign('product_id_2')->references('product_id')->on('m_products');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_covarian');
    }
}
