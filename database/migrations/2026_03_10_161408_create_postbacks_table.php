<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('postbacks', function (Blueprint $table) {
      $table->id();
      $table->uuid('uuid')->unique();
      $table->string('name');
      $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
      $table->text('base_url');
      $table->json('param_mappings')->default('{}');
      $table->text('result_url')->nullable()->after('param_mappings');
      $table->foreignId('user_id')->constrained('users');
      $table->foreignId('updated_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('postbacks');
  }
};
