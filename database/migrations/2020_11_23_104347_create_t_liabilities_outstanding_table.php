<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTLiabilitiesOutstandingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_liabilities_outstanding', function (Blueprint $table) {
            $table->bigIncrements('liabilities_outstanding_id');
            $table->unsignedInteger('investor_id');
            $table->string('liabilities_id');
            $table->string('liabilities_name');
            $table->date('outstanding_date');
            $table->string('account_id');
            $table->float('outstanding_balance');
            $table->date('due_date')->nullable();
            $table->string('tenor')->nullable();
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
        Schema::dropIfExists('t_liabilities_outstanding');
    }
}
