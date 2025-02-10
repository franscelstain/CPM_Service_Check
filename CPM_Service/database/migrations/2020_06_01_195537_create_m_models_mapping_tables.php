<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMModelsMappingTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_models_mapping', function (Blueprint $table) {
            $table->increments('model_mapping_id');
            $table->unsignedInteger('model_id');
            $table->unsignedInteger('profile_id');
            $table->string('model_mapping_name', 50);
            $table->text('description')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('model_id')->references('model_id')->on('m_models');
            $table->foreign('profile_id')->references('profile_id')->on('m_risk_profiles');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_models_mapping');
    }
}
