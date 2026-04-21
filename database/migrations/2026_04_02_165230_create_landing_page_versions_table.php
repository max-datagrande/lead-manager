<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('landing_page_versions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('landing_page_id')
                ->constrained('landing_pages')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_versions');
    }
};
