<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFieldsTable extends Migration
{
    public function up()
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('label', 100);
            $table->json('validation_rules')->nullable();
            $table->smallInteger('user_id')->nullable()->unsigned();
            $table->smallInteger('updated_user_id')->nullable()->unsigned();
            $table->timestamps(); // Includes created_at and updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('fields');
    }
}

return new CreateFieldsTable;
