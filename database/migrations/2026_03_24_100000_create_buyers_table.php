<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('buyers', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->foreignId('integration_id')->unique()->constrained('integrations')->cascadeOnDelete();
      $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
      $table->boolean('is_active')->default(true);
      $table->timestamps();
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('buyers');
  }
};
