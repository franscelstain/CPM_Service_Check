<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTAssetsFreeze extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_assets_freeze', function (Blueprint $table) {
            $table->bigIncrements('asset_freeze_id');
        	$table->unsignedInteger('investor_id')->nullable();
			$table->unsignedInteger('product_id')->nullable();
			$table->string('account_no');
			$table->string('porfolio_id');
            $table->string('freeze_unit');
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
        Schema::dropIfExists('t_assets_freeze');
    }
}
