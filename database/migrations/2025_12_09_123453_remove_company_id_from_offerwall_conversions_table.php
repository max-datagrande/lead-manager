<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('offerwall_conversions') || ! Schema::hasColumn('offerwall_conversions', 'company_id')) {
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('offerwall_conversions', function (Blueprint $table) {
            $table->dropColumn('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('offerwall_conversions') || Schema::hasColumn('offerwall_conversions', 'company_id')) {
            return;
        }

        Schema::table('offerwall_conversions', function (Blueprint $table) {
            $table->foreignId('company_id')->comment('Denormalized for reporting')->constrained('companies')->cascadeOnDelete()->index();
        });
    }
};
