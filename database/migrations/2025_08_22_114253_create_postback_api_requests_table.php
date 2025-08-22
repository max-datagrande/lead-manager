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
        Schema::create('postback_api_requests', function (Blueprint $table) {
            $table->id();
            $table->string('service'); // 'natural_intelligence', 'other_api'
            $table->string('endpoint'); // URL del endpoint
            $table->string('method')->default('GET'); // GET, POST, PUT, DELETE
            $table->json('request_data')->nullable(); // Datos enviados en la petición
            $table->json('response_data')->nullable(); // Respuesta de la API
            $table->integer('status_code')->nullable(); // Código de respuesta HTTP
            $table->text('error_message')->nullable(); // Mensaje de error si aplica
            $table->integer('response_time_ms')->nullable(); // Tiempo de respuesta en ms
            $table->string('request_id')->nullable(); // ID único de la petición
            $table->string('related_type')->nullable(); // 'postback', 'report', etc.
            $table->unsignedBigInteger('related_id')->nullable(); // ID del registro relacionado
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index(['service', 'created_at']);
            $table->index(['related_type', 'related_id']);
            $table->index('status_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_requests');
    }
};
