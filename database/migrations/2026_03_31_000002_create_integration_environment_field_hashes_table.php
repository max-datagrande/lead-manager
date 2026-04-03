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
    Schema::create('integration_environment_field_hashes', function (Blueprint $table) {
      $table->id();
      $table->foreignId('integration_environment_id')->constrained('integration_environments')->cascadeOnDelete();
      $table->foreignId('field_id')->constrained('fields')->restrictOnDelete();
      $table->boolean('is_hashed')->default(false);
      $table->string('hash_algorithm')->nullable()->comment('Possible values: md5 | sha1 | sha256 | sha512 | base64 | hmac_sha256');
      $table->string('hmac_secret')->nullable()->comment('Only used when hash_algorithm = hmac_sha256');
      $table->unique(['integration_environment_id', 'field_id'], 'iefh_env_field_unique');
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('integration_environment_field_hashes');
  }
};
