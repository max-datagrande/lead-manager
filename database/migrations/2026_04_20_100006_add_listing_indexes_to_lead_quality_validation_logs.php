<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes tuned for the admin Validation Logs listing (ValidationLogController@index).
 *
 * Query patterns observed:
 *   - Default sort: created_at DESC
 *   - Filter by status (low cardinality) combined with sort by created_at
 *   - Filter by provider_id alone
 *   - Filter by integration_id (buyer) combined with sort by created_at
 *
 * Composite indexes follow the standard rule: equality columns first, range/sort column last.
 */
return new class extends Migration {
  public function up(): void
  {
    Schema::table('lead_quality_validation_logs', function (Blueprint $table) {
      $table->index(['status', 'created_at'], 'lqvl_status_created_at_idx');
      $table->index('provider_id', 'lqvl_provider_id_idx');
      $table->index(['integration_id', 'created_at'], 'lqvl_integration_created_at_idx');
    });
  }

  public function down(): void
  {
    Schema::table('lead_quality_validation_logs', function (Blueprint $table) {
      $table->dropIndex('lqvl_status_created_at_idx');
      $table->dropIndex('lqvl_provider_id_idx');
      $table->dropIndex('lqvl_integration_created_at_idx');
    });
  }
};
