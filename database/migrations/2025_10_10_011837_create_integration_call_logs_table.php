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
        Schema::create('integration_call_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('loggable');
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('status'); // e.g., 'success', 'failed'
            $table->unsignedSmallInteger('http_status_code');
            $table->unsignedInteger('duration_ms');
            $table->text('request_url');
            $table->string('request_method');
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_headers')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_call_logs');
    }
};
