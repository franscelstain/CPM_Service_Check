<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTTransHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_trans_histories', function (Blueprint $table) {
            $table->bigIncrements('trans_history_id');
            $table->unsignedInteger('investor_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('fee_product_id')->nullable();
            $table->unsignedInteger('trans_reference_id');
            $table->unsignedInteger('status_reference_id')->nullable();
            $table->unsignedInteger('type_reference_id');
            $table->unsignedInteger('investor_account_id')->nullable();
            $table->string('portfolio_id', 50)->nullable();
            $table->string('reference_no', 50)->nullable();
            $table->string('account_no', 50)->nullable();
            $table->date('transaction_date');
            $table->date('price_date')->nullable();
            $table->date('settle_date')->nullable();
            $table->date('booking_date')->nullable();
            $table->date('maturity_date')->nullable();
            $table->integer('debt_date')->nullable();
            $table->float('amount')->nullable();
            $table->float('net_amount')->nullable();
            $table->float('price')->nullable();
            $table->float('unit')->nullable();
            $table->float('percentage')->nullable();
            $table->float('fee_amount')->nullable();
            $table->float('fee_unit')->nullable();
            $table->float('tax_amount')->nullable();
            $table->float('charge')->nullable();
            $table->float('approve_amount')->nullable();
            $table->float('approve_unit')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('remark')->nullable();
            $table->string('debt_status')->nullable();
            $table->string('debt_remark')->nullable();
            $table->enum('investment_type', ['Lumpsum', 'SIP']);
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->boolean('send_wms')->default(0);
            $table->string('guid', 255)->nullable();
            $table->string('provider_remark', 255)->nullable();
            $table->string('provider_status', 255)->nullable();
            $table->string('notif_send_email', 255)->nullable();
            $table->string('notif_send_sms', 255)->nullable();
            $table->string('provider_reference', 255)->nullable();
            $table->string('wms_status')->nullable();
            $table->string('wms_remark')->nullable();
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();            
            $table->timestamps();
            $table->foreign('investor_id')->references('investor_id')->on('u_investors');
            $table->foreign('product_id')->references('product_id')->on('m_products');
            $table->foreign('fee_product_id')->references('fee_product_id')->on('m_products_fee');
            $table->foreign('trans_reference_id')->references('trans_reference_id')->on('m_trans_reference');
            $table->foreign('status_reference_id')->references('trans_reference_id')->on('m_trans_reference');
            $table->foreign('type_reference_id')->references('trans_reference_id')->on('m_trans_reference');
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
        Schema::dropIfExists('t_trans_histories');
    }
}
