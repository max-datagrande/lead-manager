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
        Schema::table('webhook_leads', function (Blueprint $table) {
            $table->renameColumn('lead_data', 'data');
            $table->tinyInteger('status')->default(0)->after('data')->comment('0 = Pending, 1 = Processed, 2 = Failed, 3 = Retry, 4 = Duplicate, 5 = Skipped');
            $table->json('response')->nullable()->after('data');
            $table->dateTime('processed_at')->nullable()->after('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_leads', function (Blueprint $table) {
            $table->renameColumn('data', 'lead_data');
            $table->dropColumn('response');
            $table->dropColumn('processed_at');
        });
    }
};
