<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMRegionsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_regions', function (Blueprint $table) {
            $table->bigIncrements('region_id');
            $table->string('region_code', 20)->unique();
            $table->string('parent_code', 20)->nullable();
            $table->string('region_name');
            $table->string('postal_code', 10)->nullable();
            $table->enum('region_type', ['Provinsi', 'Kota / Kab.', 'Kecamatan', 'Kelurahan'])->default('Provinsi');
            $table->text('description')->nullable();
            $table->enum('is_data', ['APPS', 'WS'])->default('APPS');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_regions');
    }
}
