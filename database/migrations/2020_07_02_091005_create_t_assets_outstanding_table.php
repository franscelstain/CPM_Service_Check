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
            $table->unsignedInteger('investor_id');
            $table->unsignedInteger('product_id');
            $table->string('account_no')->nullable();
            $table->date('outstanding_date')->nullable();
            $table->date('subscription_ date')->nullable();
            $table->date('due_date')->nullable();
            $table->float('outstanding_unit')->nullable();
            $table->float('balance_amount')->nullable();
            $table->float('balance_amount_wms')->nullable();
            $table->float('total_subscription')->nullable();
            $table->float('total_unit')->nullable();
            $table->float('return_amount')->nullable();
            $table->float('return_percentage')->nullable();
            $table->float('convert_balance')->nullable();
            $table->string('freeze_unit')->nullable();
            $table->boolean('is_campaign')->nullable();
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
