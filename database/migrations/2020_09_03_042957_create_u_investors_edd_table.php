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
            $table->increments('investor_edd_id');
            $table->unsignedInteger('investor_id');
            $table->date('edd_date');
            $table->string('investment_objectives')->nullable();
            $table->string('hobby')->nullable();
            $table->string('other_hobby')->nullable();
            $table->string('organization')->nullable();
            $table->string('other_organization')->nullable();
            $table->string('bank')->nullable();
            $table->string('other_bank')->nullable();
            $table->string('insurance')->nullable();
            $table->string('other_insurance')->nullable();
            $table->string('product')->nullable();
            $table->string('other_product')->nullable();
            $table->string('credit_card')->nullable();
            $table->string('other_credit_card')->nullable();
            $table->string('relation_name')->nullable();
            $table->string('relation_type')->nullable();
            $table->string('relation_work')->nullable();
            $table->text('relation_office')->nullable();
            $table->boolean('is_investor_relation')->nullable();
            $table->string('media_name')->nullable();
            $table->text('conclusion_desc')->nullable();
            $table->string('marketing')->nullable();
            $table->string('marketing_recommendation')->nullable();
            $table->text('marketing_recommendation_desc')->nullable();
            $table->string('branch_manager')->nullable();
            $table->boolean('agreed_create_account')->nullable();
            $table->text('branch_manager_recommendation_desc')->nullable();
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
