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
        Schema::table('offerwall_conversions', function (Blueprint $table) {
            $table->foreignId('offerwall_mix_log_id')->nullable()->constrained('offerwall_mix_logs')->nullOnDelete();
            $table->jsonb('offer_data')->nullable()->comment('Snapshot of the offer data at conversion time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offerwall_conversions', function (Blueprint $table) {
            $table->dropForeign(['offerwall_mix_log_id']);
            $table->dropColumn(['offerwall_mix_log_id', 'offer_data']);
        });
    }
};
