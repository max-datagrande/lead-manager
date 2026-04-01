<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S8 — Cleanup legacy token system:
 *  - Drop `request_mapping_config` column from `integrations`
 *  - Drop legacy `field_mappings` table (unused, replaced by `integration_field_mappings`)
 *
 * Prerequisites: S7 data migration must have run first so no active data lives
 * in these structures.
 */
return new class extends Migration
{
  public function up(): void
  {
    Schema::table('integrations', function (Blueprint $table) {
      $table->dropColumn('request_mapping_config');
    });

    Schema::dropIfExists('field_mappings');
  }

  public function down(): void
  {
    Schema::table('integrations', function (Blueprint $table) {
      $table->json('request_mapping_config')->nullable()->after('type');
    });

    Schema::create('field_mappings', function (Blueprint $table) {
      $table->id();
      $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
      $table->string('external_parameter')->nullable();
      $table->string('type')->nullable();
      $table->foreignId('field_id')->nullable()->constrained('fields')->nullOnDelete();
      $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('updated_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
    });
  }
};
