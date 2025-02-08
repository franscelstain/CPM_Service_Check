<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTTransHistoriesDaysTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('t_trans_histories_days', function (Blueprint $table) {
            $table->bigIncrements('trans_history_day_id');
            $table->unsignedInteger('investor_id');
            $table->unsignedInteger('product_id');
            $table->string('portfolio_id', 50)->nullable();
            $table->string('account_no', 50)->nullable();
            $table->date('history_date');
            $table->float('unit')->nullable();
            $table->float('avg_nav')->nullable();
            $table->float('current_balance')->nullable();
            $table->float('investment_amount')->nullable();
            $table->float('earnings')->nullable();
            $table->float('returns')->nullable();
            $table->float('total_sub_amount')->nullable();
            $table->float('total_sub_unit')->nullable();
            $table->boolean('diversification_account')->nullable();
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('t_trans_histories_days');
    }
}
