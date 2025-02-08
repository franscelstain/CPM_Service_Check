<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMProductsPeriodTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_products_period', function (Blueprint $table) {
            $table->bigIncrements('period_id');
            $table->unsignedBigInteger('product_id')->unique();
            $table->date('period_date');
            $table->float('price');
            $table->float('return_1day')->nullable();
            $table->float('return_3day')->nullable();
            $table->float('return_1month')->nullable();
            $table->float('return_3month')->nullable();
            $table->float('return_6month')->nullable();
            $table->float('return_1year')->nullable();
            $table->float('return_3year')->nullable();
            $table->float('return_5year')->nullable();
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
        Schema::dropIfExists('m_products_period');
    }
}
