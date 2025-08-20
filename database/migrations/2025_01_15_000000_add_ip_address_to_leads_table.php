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
        Schema::table('leads', function (Blueprint $table) {
            // Agregar columna ip_address con la misma definición que en traffic_logs
            $table->string('ip_address', 45)->nullable()->after('fingerprint');
            
            // Agregar índice para mejorar performance en consultas
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Eliminar índice primero
            $table->dropIndex(['ip_address']);
            
            // Eliminar columna
            $table->dropColumn('ip_address');
        });
    }
};