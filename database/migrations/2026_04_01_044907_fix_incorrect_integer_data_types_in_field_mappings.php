<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix data_type values that were incorrectly inferred as 'integer' during the S7
 * token migration (from {int:field_name} patterns in legacy request bodies).
 *
 * Affected records identified from production data:
 *
 *  - user_agent  (field_id=32): UA string, never numeric
 *  - homeowner   (field_id=31): values like "own"/"rent", not numeric
 *  - age         (field_id=28): default "18-24" is a range string, not an integer
 */
return new class extends Migration {
  public function up(): void
  {
    // user_agent — field_id 32, all integrations
    DB::table('integration_field_mappings')
      ->where('field_id', 32)
      ->where('data_type', 'integer')
      ->update(['data_type' => 'string']);

    // homeowner — field_id 31, all integrations
    DB::table('integration_field_mappings')
      ->where('field_id', 31)
      ->where('data_type', 'integer')
      ->update(['data_type' => 'string']);

    // age — only when default_value is a range like "18-24", not a plain integer
    DB::table('integration_field_mappings')
      ->where('field_id', 28)
      ->where('data_type', 'integer')
      ->where('default_value', 'like', '%-%')
      ->update(['data_type' => 'string']);
  }

  public function down(): void
  {
    DB::table('integration_field_mappings')
      ->whereIn('field_id', [31, 32])
      ->where('data_type', 'string')
      ->update(['data_type' => 'integer']);

    DB::table('integration_field_mappings')
      ->where('field_id', 28)
      ->where('data_type', 'string')
      ->where('default_value', 'like', '%-%')
      ->update(['data_type' => 'integer']);
  }
};
