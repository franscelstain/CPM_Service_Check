<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMFinancialsAssetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_financials_assets', function (Blueprint $table) {
            $table->increments('financial_asset_id');
            $table->unsignedInteger('asset_class_id');
            $table->unsignedInteger('financial_id');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('asset_class_id')->references('asset_class_id')->on('m_asset_class');
            $table->foreign('financial_id')->references('financial_id')->on('m_financials');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_financials_assets');
    }
}
