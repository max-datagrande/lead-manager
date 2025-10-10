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
        Schema::create('offerwall_mix_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offerwall_mix_id')->constrained('offerwall_mixes')->cascadeOnDelete();
            $table->string('fingerprint')->index();
            $table->string('origin')->nullable()->index();
            $table->unsignedInteger('total_integrations')->default(0);
            $table->unsignedInteger('successful_integrations')->default(0);
            $table->unsignedInteger('failed_integrations')->default(0);
            $table->unsignedInteger('total_offers_aggregated')->default(0);
            $table->unsignedInteger('total_duration_ms')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offerwall_mix_logs');
    }
};
