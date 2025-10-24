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
            $table->string('platform')->nullable()->after('utm_content');
            $table->string('channel')->nullable()->after('platform');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traffic_logs', function (Blueprint $table) {
            $table->dropColumn(['platform', 'channel']);
        });
    }
};
