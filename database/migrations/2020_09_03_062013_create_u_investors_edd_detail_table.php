<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUInvestorsEddDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors_edd_detail', function (Blueprint $table) {
            $table->bigIncrements('investor_edd_detail_id');
            $table->unsignedBigInteger('investor_edd_id');
            $table->unsignedBigInteger('investor_id');
            $table->string('media_name');
            $table->text('result_description');
            $table->string('marketing_name');
            $table->boolean('recommendation');
            $table->text('recommendation_description');
            $table->string('branch_manager');
            $table->boolean('open_account');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
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
        Schema::dropIfExists('u_investors_edd_detail');
    }
}
