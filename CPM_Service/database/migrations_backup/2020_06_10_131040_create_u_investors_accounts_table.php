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
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->unsignedBigInteger('bank_branch_id')->nullable();
            $table->unsignedBigInteger('account_type_id', 100)->nullable();
            $table->string('account_name');
            $table->string('account_no', 50);
            $table->string('ext_code', 50);
            $table->enum('is_data', ['APPS', 'WS'])->default('APPS');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
            $table->foreign('currency_id')->references('currency_id')->on('m_currency');
            $table->foreign('bank_branch_id')->references('bank_branch_id')->on('m_bank_branches');
            $table->foreign('account_type_id')->references('account_type_id')->on('m_account_types');
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
