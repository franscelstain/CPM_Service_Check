<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrateUInvestorsHistoriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors_histories', function (Blueprint $table) {
            $table->increments('investor_history_id');
            $table->unsignedInteger('investor_id');
            $table->unsignedInteger('usercategory_id');
            $table->unsignedInteger('profile_id')->nullable();
            $table->unsignedInteger('gender_id')->nullable();
            $table->unsignedInteger('nationality_id')->nullable();
            $table->unsignedInteger('marital_id')->nullable();
            $table->unsignedInteger('education_id')->nullable();
            $table->unsignedInteger('occupation_id')->nullable();
            $table->unsignedInteger('religion_id')->nullable();
            $table->unsignedInteger('ethnic_id')->nullable();
            $table->unsignedInteger('fund_source_id')->nullable();
            $table->unsignedInteger('earning_id')->nullable();
            $table->unsignedInteger('investobj_id')->nullable();
            $table->unsignedInteger('doctype_id')->nullable();
            $table->unsignedInteger('sales_id')->nullable();
            $table->date('profile_effective_date')->nullable();
            $table->date('profile_expired_date')->nullable();
            $table->string('cif', 25)->nullable();
            $table->string('sid')->nullable();
            $table->string('sid_corp')->nullable();
            $table->string('sid_gov')->nullable();
            $table->string('ifua')->nullable();
            $table->string('aid_no')->nullable();
            $table->string('sub_reg')->nullable();
            $table->string('fullname');
            $table->string('place_of_birth')->nullable();
            $table->date('date_of_birth');
            $table->string('photo_profile');
            $table->string('identity_no', 50);
            $table->date('identity_expired_date')->nullable();
            $table->string('tax_no', 21)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('mobile_phone', 20)->nullable();
            $table->string('company_phone', 20)->nullable();
            $table->string('fax', 20)->nullable();
            $table->string('email_personal')->nullable();
            $table->string('email', 100);
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->integer('otp')->nullable();
            $table->timestamp('otp_created')->nullable();
            $table->enum('valid_account', ['No', 'Yes'])->default('No');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->timestamp('time_log')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('u_investors_histories');
    }
}
