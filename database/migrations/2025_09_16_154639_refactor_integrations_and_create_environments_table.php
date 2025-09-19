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
        // Step 1: Add the new 'type' column to the integrations table
        Schema::table('integrations', function (Blueprint $table) {
            $table->enum('type', ['ping-post', 'post-only', 'offerwall'])->after('name');
        });

        // Step 2: Create the new integration_environments table
        Schema::create('integration_environments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integration_id')->constrained()->onDelete('cascade');
            $table->string('environment', 50)->comment('e.g., development, production');
            $table->string('method', 100);
            $table->text('url');
            $table->text('request_body')->nullable();
            $table->text('request_headers')->nullable();
            $table->string('content_type')->nullable();
            $table->string('authentication_type')->nullable();
            $table->timestamps();

            $table->unique(['integration_id', 'environment']);
        });

        // Step 3: Drop the old columns from the integrations table
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn([
                'method',
                'environment',
                'test_url',
                'production_url',
                'request_body',
                'request_headers',
                'content_type',
                'authentication_type',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add the old columns back to the integrations table
        Schema::table('integrations', function (Blueprint $table) {
            $table->string('method', 100);
            $table->boolean('environment')->default(0)->comment('0 = DEV, 1 = PROD');
            $table->text('test_url');
            $table->text('production_url');
            $table->text('request_body');
            $table->text('request_headers');
            $table->string('content_type');
            $table->string('authentication_type');
        });

        // Step 2: Drop the integration_environments table
        Schema::dropIfExists('integration_environments');

        // Step 3: Drop the 'type' column from the integrations table
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};