<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTAssetsOutstandingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_assets_outstanding', function (Blueprint $table) {
            $table->bigIncrements('outstanding_id');
            $table->unsignedBigInteger('investor_id');
            $table->unsignedBigInteger('product_id');
            $table->string('account_no');
            $table->date('outstanding_date');
            $table->date('subscription_ date');
            $table->date('due_date');
            $table->float('outstanding_unit');
            $table->float('balance_amount');
            $table->float('balance_amount_wms');
            $table->float('return_amount')->nullable();
            $table->float('return_percentage')->nullable();
            $table->boolean('is_campaign');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
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
        Schema::dropIfExists('t_assets_outstanding');
    }
}
