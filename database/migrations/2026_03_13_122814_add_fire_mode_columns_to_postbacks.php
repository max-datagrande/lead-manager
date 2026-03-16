<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postbacks', function (Blueprint $table): void {
            $table->string('fire_mode', 20)->default('realtime')->after('result_url');
            $table->boolean('is_active')->default(true)->after('fire_mode');
            $table->unsignedBigInteger('total_executions')->default(0)->after('is_active');
            $table->timestamp('last_fired_at')->nullable()->after('total_executions');
        });
    }

    public function down(): void
    {
        Schema::table('postbacks', function (Blueprint $table): void {
            $table->dropColumn(['fire_mode', 'is_active', 'total_executions', 'last_fired_at']);
        });
    }
};
