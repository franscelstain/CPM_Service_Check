<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUInvestorsEddTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors_edd', function (Blueprint $table) {
            $table->bigIncrements('investor_edd_id');
            $table->unsignedBigInteger('investor_id');
            $table->date('edd_date');
            $table->string('family_name');
            $table->date('date_of_birth');
            $table->string('occupation');
            $table->string('salary');
            $table->boolean('is_investor');
            $table->string('relationship');
            $table->text('office_address');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('u_investors_edd');
    }
}
