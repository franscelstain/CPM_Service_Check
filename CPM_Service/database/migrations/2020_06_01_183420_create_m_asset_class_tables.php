<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMAssetClassTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_asset_class', function (Blueprint $table) {
            $table->increments('asset_class_id');
            $table->unsignedInteger('asset_category_id')->nullable();
            $table->string('asset_class_code', 50);
            $table->string('asset_class_name', 50);
            $table->string('asset_class_color', 50)->nullable();
            $table->enum('asset_class_type', ['Cash', 'Growth', 'Income'])->default('Cash');
            $table->integer('sequence_to');
            $table->text('description')->nullable();
            $table->enum('is_data', ['APPS', 'WS'])->default('APPS');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('asset_category_id')->references('asset_category_id')->on('m_asset_categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_asset_class');
    }
}
