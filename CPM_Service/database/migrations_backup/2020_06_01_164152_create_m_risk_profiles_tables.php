<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMRiskProfilesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_risk_profiles', function (Blueprint $table) {
            $table->bigIncrements('profile_id', 10);
            $table->string('profile_name');
            $table->string('profile_image')->nullable();
            $table->integer('min');
            $table->integer('max');
            $table->string('ext_code', 50)->nullable();
            $table->integer('sequence_to');
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
        Schema::dropIfExists('m_risk_profiles');
    }
}
