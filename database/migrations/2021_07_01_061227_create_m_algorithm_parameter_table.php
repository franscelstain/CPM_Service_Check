<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMAlgorithmParameterTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_algorithm_parameter', function (Blueprint $table) {
            $table->increments('algorithm_parameter_id');
            $table->unsignedInteger('asset_class_id')->nullable();
            $table->string('algorithm_parameter_name');
            $table->string('algorithm_parameter_type');
            $table->float('value')->nullable();
            $table->text('description')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('asset_class_id')->references('asset_class_id')->on('m_asset_class');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_algorithm_parameter');
    }
}
