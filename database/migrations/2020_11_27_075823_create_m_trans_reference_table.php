<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMTransReferenceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_trans_reference', function (Blueprint $table) {
            $table->bigIncrements('trans_reference_id');
            $table->enum('reference_type', ['Transaction Status', 'Goals Status', 'Transaction Type']);
            $table->string('reference_name');
            $table->string('reference_code');
            $table->string('reference_color', 50)->nullable();
            $table->json('reference_ext')->nullable();
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
        Schema::dropIfExists('m_trans_reference');
    }
}
