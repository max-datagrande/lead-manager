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
        Schema::create('whitelist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 100)->comment('Type: domain or IP');
            $table->string('name')->comment('Descriptive name of the entry');
            $table->string('value')->comment('Domain or IP value');
            $table->boolean('is_active')->default(true)->comment('Active status of the entry');
            $table->timestamps();

            // Índices para optimizar consultas
            $table->unique(['type', 'value'], 'unique_type_value');
            $table->index('type', 'idx_whitelist_entries_type');
            $table->index('value', 'idx_whitelist_entries_value');
            $table->index('is_active', 'idx_whitelist_entries_is_active');
            $table->index('created_at', 'idx_whitelist_entries_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whitelist_entries');
    }
};
