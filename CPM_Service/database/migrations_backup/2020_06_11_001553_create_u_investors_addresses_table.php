<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUInvestorsAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors_addresses', function (Blueprint $table) {
            $table->bigIncrements('investor_address_id');
            $table->unsignedBigInteger('investor_id');
            $table->unsignedBigInteger('province_id');
            $table->unsignedBigInteger('city_id');
            $table->unsignedBigInteger('subdistrict_id');
            $table->integer('postal_code');
            $table->text('address');
            $table->enum('address_type', ['domicile', 'idcard', 'mailing'])->default('idcard');
            $table->enum('is_data', ['APPS', 'WS'])->default('APPS');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
            $table->foreign('province_id')->references('region_id')->on('m_regions');
            $table->foreign('city_id')->references('region_id')->on('m_regions');
            $table->foreign('subdistrict_id')->references('region_id')->on('m_regions');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('u_investors_addresses');
    }
}
