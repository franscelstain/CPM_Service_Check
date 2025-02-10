<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUInvestorsPasswordAttemptTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors_password_attempt', function (Blueprint $table) {
           $table->increments('investor_password_attempt_id');
            $table->unsignedInteger('investor_id');
            $table->string('attempt_count', 50);
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
        Schema::dropIfExists('u_investors_password_attempt');
    }
}
