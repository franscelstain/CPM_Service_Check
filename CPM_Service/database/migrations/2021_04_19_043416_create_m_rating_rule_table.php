<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMRatingRuleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_rating_rule', function (Blueprint $table) {
            $table->increments('rating_rule_id');
            $table->unsignedInteger('asset_class_id');
            $table->unsignedInteger('parameter_maintain_id');
            $table->string('rating_rule_criteria'); 
            $table->float('rating_rule_value_1');
            $table->float('rating_rule_value_2')->nullable();
            $table->float('rating_rule_score');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
            $table->timestamps();
            $table->foreign('asset_class_id')->references('asset_class_id')->on('m_asset_class');
            $table->foreign('parameter_maintain_id')->references('parameter_maintain_id')->on('m_parameter_maintain_data');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('m_rating_rule');
    }
}
