<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUInvestorsCardPrioritiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors_card_priorities', function (Blueprint $table) {
            $table->bigIncrements('investor_card_id');
            $table->unsignedBigInteger('investor_category_id');
            $table->unsignedBigInteger('investor_card_type_id')->nullable();
            $table->string('cif', 25)->nullable();
            $table->date('card_expired')->nullable(); 
            $table->boolean('pre_approve')->default();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_category_id')->references('investor_category_id')->on('u_investors_categories');
            $table->foreign('investor_card_type_id')->references('investor_card_type_id')->on('u_investors_card_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('u_investors_card_priorities');
    }
}
