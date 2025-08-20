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
            // Agregar columnas para tracking de campañas
            $table->string('campaign_code')->nullable()->comment('Campaign code extracted from query_params.cptype');
            $table->string('campaign_id')->nullable()->comment('Campaign ID for tracking');
            $table->string('click_id')->nullable()->comment('Unique click ID for conversion tracking');
            
            // Agregar índices para mejorar performance en consultas
            $table->index('campaign_code');
            $table->index('campaign_id');
            $table->index('click_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traffic_logs', function (Blueprint $table) {
            // Eliminar índices primero
            $table->dropIndex(['campaign_code']);
            $table->dropIndex(['campaign_id']);
            $table->dropIndex(['click_id']);
            
            // Eliminar columnas
            $table->dropColumn(['campaign_code', 'campaign_id', 'click_id']);
        });
    }
};
