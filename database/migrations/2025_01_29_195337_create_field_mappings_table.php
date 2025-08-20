<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFieldMappingsTable extends Migration
{
    public function up()
    {
        Schema::create('field_mappings', function (Blueprint $table) {
            $table->id();
            $table->integer('integration_id')->unsigned();
            $table->string('external_parameter', 100);
            $table->string('type', 20)->default('string')->comment('string, integer, boolean');
            $table->integer('field_id')->unsigned();
            $table->smallInteger('user_id')->nullable()->unsigned();
            $table->smallInteger('updated_user_id')->nullable()->unsigned();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('field_mappings');
    }
}

return new CreateFieldMappingsTable;
