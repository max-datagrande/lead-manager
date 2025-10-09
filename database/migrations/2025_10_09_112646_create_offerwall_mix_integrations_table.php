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
        Schema::create('offerwall_mix_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offerwall_mix_id')->constrained()->onDelete('cascade');
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['offerwall_mix_id', 'integration_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offerwall_mix_integrations');
    }
};
