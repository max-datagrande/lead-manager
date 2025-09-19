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
        Schema::table('postbacks', function (Blueprint $table) {
            $table->renameColumn('failure_reason', 'message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('postbacks', function (Blueprint $table) {
            $table->renameColumn('message', 'failure_reason');
        });
    }
};