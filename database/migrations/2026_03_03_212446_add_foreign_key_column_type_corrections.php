<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Correct column types so foreign keys can be created.
     * Widening smallint/integer → bigint is safe and lossless.
     */
    public function up(): void
    {
        // --- smallint(16) → bigint(64) for user_id / updated_user_id columns ---

        Schema::table('companies', function (Blueprint $table) {
            $table->bigInteger('user_id')->nullable()->change();
            $table->bigInteger('updated_user_id')->nullable()->change();
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->bigInteger('company_id')->nullable()->change(); // nullable for SET NULL on delete
            $table->bigInteger('user_id')->nullable()->change();
            $table->bigInteger('updated_user_id')->nullable()->change();
        });

        Schema::table('fields', function (Blueprint $table) {
            $table->bigInteger('user_id')->nullable()->change();
            $table->bigInteger('updated_user_id')->nullable()->change();
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->bigInteger('user_id')->nullable()->change();
            $table->bigInteger('updated_user_id')->nullable()->change();
        });

        Schema::table('field_mappings', function (Blueprint $table) {
            $table->bigInteger('integration_id')->change();
            $table->bigInteger('field_id')->change();
            $table->bigInteger('user_id')->nullable()->change();
            $table->bigInteger('updated_user_id')->nullable()->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->bigInteger('user_id')->nullable()->change();
        });

        // --- integer(32) → bigint(64) for FK reference columns ---

        Schema::table('lead_field_responses', function (Blueprint $table) {
            $table->bigInteger('lead_id')->change();
            $table->bigInteger('field_id')->change();
        });

        Schema::table('field_form', function (Blueprint $table) {
            $table->bigInteger('field_id')->change();
            $table->bigInteger('form_id')->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->bigInteger('sale_id')->change();
        });
    }

    /**
     * Reverse the column type changes.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->smallInteger('user_id')->nullable()->change();
            $table->smallInteger('updated_user_id')->nullable()->change();
        });

        Schema::table('integrations', function (Blueprint $table) {
            $table->integer('company_id')->nullable(false)->change();
            $table->smallInteger('user_id')->nullable()->change();
            $table->smallInteger('updated_user_id')->nullable()->change();
        });

        Schema::table('fields', function (Blueprint $table) {
            $table->smallInteger('user_id')->nullable()->change();
            $table->smallInteger('updated_user_id')->nullable()->change();
        });

        Schema::table('forms', function (Blueprint $table) {
            $table->smallInteger('user_id')->nullable()->change();
            $table->smallInteger('updated_user_id')->nullable()->change();
        });

        Schema::table('field_mappings', function (Blueprint $table) {
            $table->integer('integration_id')->change();
            $table->integer('field_id')->change();
            $table->smallInteger('user_id')->nullable()->change();
            $table->smallInteger('updated_user_id')->nullable()->change();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->smallInteger('user_id')->nullable()->change();
        });

        Schema::table('lead_field_responses', function (Blueprint $table) {
            $table->integer('lead_id')->change();
            $table->integer('field_id')->change();
        });

        Schema::table('field_form', function (Blueprint $table) {
            $table->integer('field_id')->change();
            $table->integer('form_id')->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('sale_id')->change();
        });
    }
};
