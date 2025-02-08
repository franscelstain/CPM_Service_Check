<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_clients', function (Blueprint $table) {
            $table->increments('client_id');
            $table->unsignedInteger('currency_id');
			$table->string('client_name');
			$table->string('office_address');
			$table->string('call_center');
			$table->string('no_telephone');
			$table->string('wa_number');
			$table->string('fax');
			$table->string('email');
			$table->string('main_currency');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('currency_id')->references('currency_id')->on('m_currency');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_clients');
    }
}
