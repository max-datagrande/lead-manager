<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones para crear tabla de logs de conversiones
     */
    public function up(): void
    {
        Schema::create('conversion_logs', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint')->index(); // Relación con leads
            $table->enum('event_type', ['clickout', 'funnel_completion']);
            $table->string('vendor', 63);
            $table->string('campaign_id', 63);
            $table->decimal('price', 10, 2);
            $table->string('click_id');
            
            // Parámetros personalizados opcionales
            $table->string('s1')->nullable();
            $table->string('s2')->nullable();
            $table->string('s3')->nullable();
            $table->string('s4')->nullable();
            
            // Estado del postback
            $table->enum('postback_status', ['pending', 'sent', 'failed', 'retrying'])->default('pending');
            $table->text('postback_response')->nullable(); // Respuesta de NI
            $table->timestamp('postback_sent_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('error_message')->nullable();
            
            $table->timestamps();
            
            // Índices para performance
            $table->index(['fingerprint', 'event_type']);
            $table->index('postback_status');
            $table->index('created_at');
            
            // Constraint para prevenir duplicados exactos
            $table->unique(['fingerprint', 'event_type', 'campaign_id'], 'unique_conversion');
        });
    }

    /**
     * Revierte las migraciones
     */
    public function down(): void
    {
        Schema::dropIfExists('conversion_logs');
    }
};