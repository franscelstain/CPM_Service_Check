<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMProductsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_products', function (Blueprint $table) {
            $table->bigIncrements('product_id');
            $table->unsignedBigInteger('asset_class_id')->nullable();
            $table->unsignedBigInteger('issuer_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->unsignedBigInteger('thirdparty_id')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->unsignedBigInteger('dividen_id')->nullable();
            $table->string('product_code', 25);
            $table->string('product_name', 100);
            $table->enum('product_type', ['Non Tradeable', 'Tradeable'])->default('Tradeable');
            $table->date('offering_period_start')->nullable();
            $table->date('offering_period_end')->nullable();
            $table->date('exit_windows_start')->nullable();
            $table->date('exit_windows_end')->nullable();
            $table->date('maturity_date')->nullable();
            $table->date('launch_date');
            $table->boolean('allow_new_sub')->nullable();
            $table->boolean('allow_redeem')->nullable();
            $table->boolean('allow_switching')->nullable();
            $table->boolean('allow_topup')->nullable();
            $table->boolean('allow_sip')->nullable();
            $table->float('min_buy')->nullable();
            $table->float('max_buy')->nullable();
            $table->float('min_sell')->nullable();
            $table->float('max_sell')->nullable();
            $table->float('multiple_purchase')->nullable();
            $table->float('aum')->nullable();
            $table->string('ext_code', 50)->nullable();
            $table->enum('is_data', ['APPS', 'WS'])->default('APPS');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('asset_class_id')->references('asset_class_id')->on('m_asset_class');
            $table->foreign('issuer_id')->references('issuer_id')->on('m_issuer');
            $table->foreign('currency_id')->references('currency_id')->on('m_currency');
            $table->foreign('profile_id')->references('profile_id')->on('m_risk_profiles');
            $table->foreign('thirdparty_id')->references('thirdparty_id')->on('m_thirdparty');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_products');
    }
}
