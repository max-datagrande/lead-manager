<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeadsTable extends Migration
{
    public function up()
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint', 100);
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('zip', 100)->nullable();
            $table->string('website')->nullable();
            $table->timestamps(); // Includes created_at and updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('leads');
    }
}

return new CreateLeadsTable;
