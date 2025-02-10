<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUInvestorsEddFamiliesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors_edd_families', function (Blueprint $table) {
            $table->increments('investor_edd_family_id');
            $table->unsignedInteger('investor_edd_id');
            $table->string('family_name');
            $table->date('date_of_birth')->nullable();
            $table->string('occupation')->nullable();
            $table->float('salary')->nullable();
            $table->boolean('is_investor')->nullable();
            $table->string('relationship');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_edd_id')->references('investor_edd_id')->on('u_investors_edd');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('u_investors_edd_families');
    }
}
