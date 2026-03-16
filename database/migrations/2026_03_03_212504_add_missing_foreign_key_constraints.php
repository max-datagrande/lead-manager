<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
  /**
   * Add missing foreign key constraints using NOT VALID + VALIDATE CONSTRAINT
   * to avoid ACCESS EXCLUSIVE locks that would block production traffic.
   *
   * Strategy:
   *   1. Clean orphaned rows in small chunks (avoids long-held row locks).
   *   2. ADD CONSTRAINT ... NOT VALID  → brief lock, no table scan.
   *   3. VALIDATE CONSTRAINT           → ShareUpdateExclusiveLock, allows
   *      concurrent reads and writes while scanning existing rows.
   */
  public function up(): void
  {
    // ---------------------------------------------------------------
    // STEP 1: Clean orphaned records in chunks
    // ---------------------------------------------------------------

    $this->deleteOrphansInChunks('lead_field_responses', 'lead_id', 'leads');
    $this->deleteOrphansInChunks('lead_field_responses', 'field_id', 'fields');
    $this->deleteOrphansInChunks('postback_api_requests', 'postback_id', 'postbacks', nullable: true);

    $this->nullifyOrphansInChunks('fields', 'user_id', 'users');
    $this->nullifyOrphansInChunks('fields', 'updated_user_id', 'users');

    // ---------------------------------------------------------------
    // STEP 2: ADD CONSTRAINT ... NOT VALID
    // Only takes a brief lock — does NOT scan existing rows.
    // New writes will be validated immediately from this point on.
    // ---------------------------------------------------------------

    // NOT VALID + VALIDATE CONSTRAINT are PostgreSQL-specific — skip on SQLite (tests).
    if (DB::getDriverName() !== 'sqlite') {
      foreach ($this->constraintDefinitions() as [$table, $name, $column, $refTable, $action]) {
        DB::statement(sprintf(
          'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(id) ON DELETE %s NOT VALID',
          $table,
          $name,
          $column,
          $refTable,
          $action,
        ));
      }

      // ---------------------------------------------------------------
      // STEP 3: VALIDATE CONSTRAINT
      // Acquires ShareUpdateExclusiveLock — allows concurrent reads and
      // writes while scanning existing rows. Safe to run in production.
      // ---------------------------------------------------------------

      foreach ($this->constraintDefinitions() as [$table, $name]) {
        DB::statement(sprintf('ALTER TABLE %s VALIDATE CONSTRAINT %s', $table, $name));
      }
    }
  }

  /**
   * Drop all foreign key constraints added by this migration.
   */
  public function down(): void
  {
    foreach ($this->constraintDefinitions() as [$table, $name]) {
      DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $name));
    }
  }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

  /**
   * Delete orphaned rows in chunks to avoid long-held table locks.
   */
  private function deleteOrphansInChunks(
    string $table,
    string $column,
    string $referencedTable,
    bool $nullable = false,
    int $chunkSize = 500,
  ): void {
    do {
      $query = DB::table($table)
        ->whereNotExists(function ($q) use ($table, $column, $referencedTable): void {
          $q->select(DB::raw(1))
            ->from($referencedTable)
            ->whereColumn("{$referencedTable}.id", "{$table}.{$column}");
        });

      if ($nullable) {
        $query->whereNotNull($column);
      }

      $deleted = $query->limit($chunkSize)->delete();
    } while ($deleted > 0);
  }

  /**
   * Nullify orphaned foreign key values in chunks.
   */
  private function nullifyOrphansInChunks(
    string $table,
    string $column,
    string $referencedTable,
    int $chunkSize = 500,
  ): void {
    do {
      $updated = DB::table($table)
        ->whereNotNull($column)
        ->whereNotExists(function ($q) use ($table, $column, $referencedTable): void {
          $q->select(DB::raw(1))
            ->from($referencedTable)
            ->whereColumn("{$referencedTable}.id", "{$table}.{$column}");
        })
        ->limit($chunkSize)
        ->update([$column => null]);
    } while ($updated > 0);
  }

  /**
   * All FK constraint definitions.
   *
   * Format: [table, constraint_name, column, references_table, on_delete_action]
   *
   * @return array<int, array{string, string, string, string, string}>
   */
  private function constraintDefinitions(): array
  {
    return [
      // lead_field_responses
      ['lead_field_responses', 'lead_field_responses_lead_id_foreign',  'lead_id',  'leads',  'CASCADE'],
      ['lead_field_responses', 'lead_field_responses_field_id_foreign', 'field_id', 'fields', 'CASCADE'],

      // field_form pivot
      ['field_form', 'field_form_field_id_foreign', 'field_id', 'fields', 'CASCADE'],
      ['field_form', 'field_form_form_id_foreign',  'form_id',  'forms',  'CASCADE'],

      // field_mappings
      ['field_mappings', 'field_mappings_integration_id_foreign',  'integration_id',  'integrations', 'RESTRICT'],
      ['field_mappings', 'field_mappings_field_id_foreign',         'field_id',        'fields',       'RESTRICT'],
      ['field_mappings', 'field_mappings_user_id_foreign',          'user_id',         'users',        'SET NULL'],
      ['field_mappings', 'field_mappings_updated_user_id_foreign',  'updated_user_id', 'users',        'SET NULL'],

      // postback_api_requests
      ['postback_api_requests', 'postback_api_requests_postback_id_foreign', 'postback_id', 'postbacks', 'CASCADE'],

      // transactions / sales
      ['transactions', 'transactions_sale_id_foreign',       'sale_id',        'sales',        'CASCADE'],
      ['sales',        'sales_integration_id_foreign',       'integration_id', 'integrations', 'SET NULL'],
      ['sales',        'sales_user_id_foreign',              'user_id',        'users',        'SET NULL'],

      // integrations
      ['integrations', 'integrations_company_id_foreign',      'company_id',      'companies', 'SET NULL'],
      ['integrations', 'integrations_user_id_foreign',         'user_id',         'users',     'SET NULL'],
      ['integrations', 'integrations_updated_user_id_foreign', 'updated_user_id', 'users',     'SET NULL'],

      // companies
      ['companies', 'companies_user_id_foreign',         'user_id',         'users', 'SET NULL'],
      ['companies', 'companies_updated_user_id_foreign', 'updated_user_id', 'users', 'SET NULL'],

      // fields
      ['fields', 'fields_user_id_foreign',         'user_id',         'users', 'SET NULL'],
      ['fields', 'fields_updated_user_id_foreign', 'updated_user_id', 'users', 'SET NULL'],

      // forms
      ['forms', 'forms_user_id_foreign',         'user_id',         'users', 'SET NULL'],
      ['forms', 'forms_updated_user_id_foreign', 'updated_user_id', 'users', 'SET NULL'],

      // sessions
      ['sessions', 'sessions_user_id_foreign', 'user_id', 'users', 'CASCADE'],
    ];
  }
};
