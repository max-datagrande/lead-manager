<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            // Cambiar el nombre de la columna 'zip' a 'postal_code'
            $table->renameColumn('zip', 'postal_code');
            $table->string('country_code', 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            // Revertir el cambio, si es necesario
            $table->renameColumn('postal_code', 'zip');
            $table->dropColumn('country_code');
        });
    }
};
