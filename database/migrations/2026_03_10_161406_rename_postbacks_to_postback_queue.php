<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('postbacks', 'postback_queue');
    }

    public function down(): void
    {
        Schema::rename('postback_queue', 'postbacks');
    }
};
