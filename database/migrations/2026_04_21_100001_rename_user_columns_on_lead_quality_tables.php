<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the user-tracking columns on lead_quality_* tables with the convention
 * already used across the rest of the project (e.g. AlertChannel, BuyerConfig):
 * `user_id` for the creator, `updated_user_id` for the last editor.
 *
 * `renameColumn` is used so existing rows (incl. providers already configured
 * with real credentials for testing) survive the change.
 */
return new class extends Migration {
  public function up(): void
  {
    // Idempotent guard: in fresh environments (tests, new devs) the source
    // migrations 100001/100002 already create the columns with the new names,
    // so this migration must be a no-op there. In the dev/prod databases that
    // were migrated before this rename, the old columns still exist.
    foreach (['lead_quality_providers', 'lead_quality_validation_rules'] as $tableName) {
      Schema::table($tableName, function (Blueprint $table) use ($tableName) {
        if (Schema::hasColumn($tableName, 'created_by')) {
          $table->renameColumn('created_by', 'user_id');
        }
        if (Schema::hasColumn($tableName, 'updated_by')) {
          $table->renameColumn('updated_by', 'updated_user_id');
        }
      });
    }
  }

  public function down(): void
  {
    foreach (['lead_quality_providers', 'lead_quality_validation_rules'] as $tableName) {
      Schema::table($tableName, function (Blueprint $table) use ($tableName) {
        if (Schema::hasColumn($tableName, 'user_id')) {
          $table->renameColumn('user_id', 'created_by');
        }
        if (Schema::hasColumn($tableName, 'updated_user_id')) {
          $table->renameColumn('updated_user_id', 'updated_by');
        }
      });
    }
  }
};
