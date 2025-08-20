<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadFieldResponsesTable extends Migration
{
    public function up()
    {
        Schema::create('lead_field_responses', function (Blueprint $table) {
            $table->id();
            $table->integer('lead_id')->unsigned();
            $table->integer('field_id')->unsigned();
            $table->string('value');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('lead_field_responses');
    }
}

return new CreateLeadFieldResponsesTable;
