<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postback_executions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('execution_uuid')->unique();
            $table->foreignId('postback_id')->constrained('postbacks')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->json('inbound_params')->nullable();
            $table->json('resolved_tokens')->nullable();
            $table->text('outbound_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->timestamps();

            $table->index('status');
            $table->index('next_retry_at');
            $table->index(['postback_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postback_executions');
    }
};
