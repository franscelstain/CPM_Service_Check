<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUInvestorsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('u_investors', function (Blueprint $table) {
            $table->increments('investor_id');
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
            $table->integer('wms_status_code')->nullable();
            $table->string('wms_message')->nullable();
            $table->enum('valid_account', ['No', 'Yes'])->default('No');
            $table->enum('is_enable', ['No', 'Yes'])->default('Yes');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
	    $table->enum('notif_req_sid_send_sms', ['No', 'Yes'])->default('No');
            $table->enum('notif_req_sid_send_email', ['No', 'Yes'])->default('No');
            $table->enum('notif_sid_send_sms', ['No', 'Yes'])->default('No');
            $table->enum('notif_sid_send_email', ['No', 'Yes'])->default('No');
	    $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('doctype_id')->references('doctype_id')->on('m_document_types');
            $table->foreign('education_id')->references('education_id')->on('m_educations');
            $table->foreign('earning_id')->references('earning_id')->on('m_earnings');
            $table->foreign('investobj_id')->references('investobj_id')->on('m_investment_objectives');
            $table->foreign('fund_source_id')->references('financial_id')->on('m_financials');
            $table->foreign('gender_id')->references('gender_id')->on('m_gender');
            $table->foreign('marital_id')->references('marital_id')->on('m_marital_status');
            $table->foreign('nationality_id')->references('nationality_id')->on('m_nationalities');
            $table->foreign('occupation_id')->references('occupation_id')->on('m_occupations');
            $table->foreign('profile_id')->references('profile_id')->on('m_risk_profiles');
            $table->foreign('religion_id')->references('religion_id')->on('m_religions');
            $table->foreign('ethnic_id')->references('ethnic_id')->on('m_ethnics');
            $table->foreign('usercategory_id')->references('usercategory_id')->on('u_users_categories');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('u_investors');
    }
}
