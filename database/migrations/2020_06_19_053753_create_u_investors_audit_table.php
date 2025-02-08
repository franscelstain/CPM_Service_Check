<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUInvestorsAuditTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pk_id');
            $table->string('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_type');
            $table->text('description');
            $table->enum('status', ['Insert', 'Update', 'Delete'])->default('Insert');
            $table->timestamps();
            $table->foreign('pk_id')->references('investor_id')->on('u_investors');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('u_investors_audit');
    }
}
