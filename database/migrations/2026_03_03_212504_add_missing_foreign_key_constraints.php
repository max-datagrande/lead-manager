<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add missing foreign key constraints across all tables.
     * Cleans orphaned data before creating constraints.
     */
    public function up(): void
    {
        // ---------------------------------------------------------------
        // STEP 1: Clean orphaned records that would block FK creation
        // ---------------------------------------------------------------

        // lead_field_responses → leads (CASCADE): delete orphans
        DB::table('lead_field_responses')
            ->whereNotIn('lead_id', DB::table('leads')->select('id'))
            ->delete();

        // lead_field_responses → fields (CASCADE): delete orphans
        DB::table('lead_field_responses')
            ->whereNotIn('field_id', DB::table('fields')->select('id'))
            ->delete();

        // postback_api_requests → postbacks (CASCADE): delete orphans
        DB::table('postback_api_requests')
            ->whereNotNull('postback_id')
            ->whereNotIn('postback_id', DB::table('postbacks')->select('id'))
            ->delete();

        // fields.user_id → users (SET NULL): nullify orphans
        DB::table('fields')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', DB::table('users')->select('id'))
            ->update(['user_id' => null]);

        DB::table('fields')
            ->whereNotNull('updated_user_id')
            ->whereNotIn('updated_user_id', DB::table('users')->select('id'))
            ->update(['updated_user_id' => null]);

        // ---------------------------------------------------------------
        // STEP 2: Create FK constraints — Leads & Fields domain
        // ---------------------------------------------------------------

        // lead_field_responses.lead_id → leads.id (CASCADE)
        Schema::table('lead_field_responses', function (Blueprint $table) {
            $table->foreign('lead_id')
                ->references('id')->on('leads')
                ->onDelete('cascade');
        });

        // lead_field_responses.field_id → fields.id (CASCADE)
        Schema::table('lead_field_responses', function (Blueprint $table) {
            $table->foreign('field_id')
                ->references('id')->on('fields')
                ->onDelete('cascade');
        });

        // ---------------------------------------------------------------
        // STEP 3: Create FK constraints — Forms domain (CASCADE)
        // ---------------------------------------------------------------

        Schema::table('field_form', function (Blueprint $table) {
            $table->foreign('field_id')
                ->references('id')->on('fields')
                ->onDelete('cascade');
            $table->foreign('form_id')
                ->references('id')->on('forms')
                ->onDelete('cascade');
        });

        // ---------------------------------------------------------------
        // STEP 4: Create FK constraints — Field Mappings (RESTRICT)
        // ---------------------------------------------------------------

        Schema::table('field_mappings', function (Blueprint $table) {
            $table->foreign('integration_id')
                ->references('id')->on('integrations')
                ->onDelete('restrict');
            $table->foreign('field_id')
                ->references('id')->on('fields')
                ->onDelete('restrict');
        });

        // ---------------------------------------------------------------
        // STEP 5: Create FK constraints — Postbacks domain (CASCADE)
        // ---------------------------------------------------------------

        Schema::table('postback_api_requests', function (Blueprint $table) {
            $table->foreign('postback_id')
                ->references('id')->on('postbacks')
                ->onDelete('cascade');
        });

        // ---------------------------------------------------------------
        // STEP 6: Create FK constraints — Sales domain
        // ---------------------------------------------------------------

        // transactions.sale_id → sales.id (CASCADE)
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('sale_id')
                ->references('id')->on('sales')
                ->onDelete('cascade');
        });

        // sales.integration_id → integrations.id (SET NULL)
        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('integration_id')
                ->references('id')->on('integrations')
                ->onDelete('set null');
        });

        // ---------------------------------------------------------------
        // STEP 7: Create FK constraints — Integrations → Companies (SET NULL)
        // ---------------------------------------------------------------

        Schema::table('integrations', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')->on('companies')
                ->onDelete('set null');
        });

        // ---------------------------------------------------------------
        // STEP 8: Create FK constraints — User tracking (SET NULL)
        // All user_id / updated_user_id columns → users.id
        // ---------------------------------------------------------------

        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
            $table->foreign('updated_user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
            $table->foreign('updated_user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        Schema::table('fields', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
            $table->foreign('updated_user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
            $table->foreign('updated_user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        Schema::table('field_mappings', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
            $table->foreign('updated_user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('set null');
        });

        // ---------------------------------------------------------------
        // STEP 9: Create FK constraint — Sessions (CASCADE)
        // ---------------------------------------------------------------

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Remove all foreign key constraints added by this migration.
     */
    public function down(): void
    {
        Schema::table('lead_field_responses', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropForeign(['field_id']);
        });

        Schema::table('field_form', function (Blueprint $table) {
            $table->dropForeign(['field_id']);
            $table->dropForeign(['form_id']);
        });

        Schema::table('field_mappings', function (Blueprint $table) {
            $table->dropForeign(['integration_id']);
            $table->dropForeign(['field_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['updated_user_id']);
        });

        Schema::table('postback_api_requests', function (Blueprint $table) {
            $table->dropForeign(['postback_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['integration_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['updated_user_id']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['updated_user_id']);
        });

        Schema::table('fields', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['updated_user_id']);
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['updated_user_id']);
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
    }
};
