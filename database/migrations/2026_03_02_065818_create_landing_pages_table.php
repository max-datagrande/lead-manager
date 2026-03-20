<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create('landing_pages', function (Blueprint $table) {
      $table->id();
      $table->string('name', 150);
      $table->string('url', 255)->unique();
      $table->boolean('is_external')->default(false);
      $table->foreignId('vertical_id')->constrained()->cascadeOnDelete();
      $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
      $table->boolean('active')->default(true);
      $table->foreignId('user_id')->constrained()->cascadeOnDelete();
      $table->foreignId('updated_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('landing_pages');
  }
};
