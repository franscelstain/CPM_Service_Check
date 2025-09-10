<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTInstallmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_installments', function (Blueprint $table) {
            $table->bigIncrements('trans_installment_id');
            $table->unsignedInteger('investor_id');
            $table->unsignedInteger('product_id');
            // $table->unsignedInteger('trans_reference_id');
            // $table->unsignedInteger('status_reference_id')->nullable();
            // $table->unsignedInteger('type_reference_id');
            $table->unsignedInteger('investor_account_id')->nullable();
            $table->string('portfolio_id', 50)->nullable();
            $table->string('account_no', 50)->nullable();
            $table->string('registered_id')->nullable();
            $table->integer('debt_date')->nullable();
            $table->integer('tenor_month')->nullable();
            $table->float('investment_amount')->nullable();
            $table->float('fee_amount')->nullable();
            $table->float('tax_amount')->nullable();
            $table->string('status', 50)->nullable();
            $table->date('start_date');
             $table->string('wms_message', 1000)->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();            
            $table->timestamps();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
            $table->foreign('product_id')->references('product_id')->on('m_products');
            // $table->foreign('trans_reference_id')->references('trans_reference_id')->on('m_trans_reference');
            // $table->foreign('status_reference_id')->references('trans_reference_id')->on('m_trans_reference');
            // $table->foreign('type_reference_id')->references('trans_reference_id')->on('m_trans_reference');       
            $table->foreign('investor_account_id')->references('investor_account_id')->on('u_investors_accounts');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_installments');
    }
}
