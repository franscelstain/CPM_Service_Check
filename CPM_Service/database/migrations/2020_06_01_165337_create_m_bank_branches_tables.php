<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMBankBranchesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_bank_branches', function (Blueprint $table) {
            $table->increments('bank_branch_id');
            $table->unsignedInteger('bank_id');
            $table->string('branch_name');
            $table->string('branch_code', 50)->nullable();
            $table->string('branch_contact_person')->nullable();
            $table->string('branch_city', 100)->nullable();
            $table->text('branch_address')->nullable();
            $table->string('branch_zip', 20)->nullable();
            $table->string('branch_phone', 20)->nullable();                               
            $table->string('branch_fax', 20)->nullable();
            $table->string('ext_code', 50)->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('bank_id')->references('bank_id')->on('m_bank');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_bank_branches');
    }
}
