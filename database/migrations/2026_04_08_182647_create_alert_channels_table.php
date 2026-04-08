<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  /**
   * Run the migrations.
   */
  public function up(): void
  {
    Schema::create('alert_channels', function (Blueprint $table) {
      $table->id();
      $table->string('name');
      $table->string('type');
      $table->text('webhook_url');
      $table->boolean('active')->default(true);
      $table->foreignId('user_id')->constrained('users');
      $table->foreignId('updated_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('alert_channels');
  }
};
