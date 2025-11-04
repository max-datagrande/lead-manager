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
        Schema::table('integrations', function (Blueprint $table) {
            $table->renameColumn('parser_config', 'response_parser_config');
        });
        Schema::table('integrations', function (Blueprint $table) {
            $table->json('request_mapping_config')->nullable()->after('response_parser_config');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('request_mapping_config');
        });
        Schema::table('integrations', function (Blueprint $table) {
            $table->renameColumn('response_parser_config', 'parser_config');
        });
    }
};
