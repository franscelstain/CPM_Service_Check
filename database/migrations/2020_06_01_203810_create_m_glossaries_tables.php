<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMGlossariesTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('m_glossaries', function (Blueprint $table) {
            $table->increments('glossary_id');
            $table->string('glossary_code', 20);
            $table->string('glossary_name', 100);
            $table->text('glossary_text');
            $table->enum('is_active', ['No', 'Yes'])->default('Yes');
            $table->string('created_by', 50);
            $table->string('updated_by', 50)->nullable();
            $table->string('created_host', 30);
            $table->string('updated_host', 30)->nullable();
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
        Schema::dropIfExists('m_glossaries');
    }
}
