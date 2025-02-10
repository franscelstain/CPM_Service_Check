<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMParameterMaintainDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_parameter_maintain_data', function (Blueprint $table) {
            $table->increments('parameter_maintain_id');
            $table->string('parameter_maintain_name')->nullable();
            $table->string('parameter_maintain_category')->nullable();
            $table->string('lookup_table')->nullable(); 
            $table->string('remarks')->nullable(); 
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
        Schema::dropIfExists('m_parameter_maintain_data');
    }
}
