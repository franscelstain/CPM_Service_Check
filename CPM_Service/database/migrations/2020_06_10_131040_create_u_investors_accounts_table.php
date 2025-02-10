<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUInvestorsAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors_accounts', function (Blueprint $table) {
            $table->bigIncrements('investor_account_id');
            $table->unsignedBigInteger('investor_id');
            $table->string('account_name');
            $table->string('account_type', 100);
            $table->string('account_no', 50);
            $table->decimal('balance', 15, 2);
            $table->string('currency', 50);
            $table->string('bank_branch');
            $table->string('card_no', 50)->nullable();
            $table->date('card_expired')->nullable();
            $table->string('ext_code', 50);
            $table->enum('is_data', ['APPS', 'WS'])->default('APPS');
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
        Schema::dropIfExists('u_investors_accounts');
    }
}
