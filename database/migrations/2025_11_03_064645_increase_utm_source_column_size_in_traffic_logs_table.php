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
            // Aumentar el tamaño de utm_source de 50 a 255 caracteres
            // para permitir valores como "other (https://trusted-claim-assistance.com/qs202510/)"
            $table->string('utm_source', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('traffic_logs', function (Blueprint $table) {
            // Revertir a 50 caracteres
            $table->string('utm_source', 50)->nullable()->change();
        });
    }
};
