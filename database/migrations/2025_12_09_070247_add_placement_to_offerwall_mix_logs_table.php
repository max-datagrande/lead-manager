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
        Schema::table('offerwall_mix_logs', function (Blueprint $table) {
            $table->string('placement')->nullable()->index()->after('origin')->comment('Location identifier where the offerwall was displayed (e.g. "thank_you_page", "popup")');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offerwall_mix_logs', function (Blueprint $table) {
            $table->dropColumn('placement');
        });
    }
};
