<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('traffic_logs', function (Blueprint $table) {
            // Cambiar la columna referrer a longText para permitir URLs muy largas
            // Esto permite hasta 4GB de texto en MySQL/PostgreSQL
            $table->longText('referrer')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traffic_logs', function (Blueprint $table) {
            // Revertir a text normal
            $table->text('referrer')->nullable()->change();
        });
    }
};
