<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMAumTargetTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_aum_target', function (Blueprint $table) {
            $table->increments('id_aum_target');
            $table->date('effective_date');
            $table->float('target_aum');
            $table->json('asset_category');
            $table->enum('status_active', ['Active', 'Inactive'])->default('Active');
            $table->enum('is_valid', ['No', 'Yes'])->default('No');
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
        Schema::dropIfExists('m_aum_target');
    }
}
