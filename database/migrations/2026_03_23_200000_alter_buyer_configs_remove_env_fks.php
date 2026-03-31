<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('buyer_configs', function (Blueprint $table): void {
      $table->dropForeign(['ping_environment_id']);
      $table->dropForeign(['post_environment_id']);
      $table->dropColumn(['ping_environment_id', 'post_environment_id']);

      $table->string('ping_url', 2048)->nullable()->after('integration_id');
      $table->string('ping_method', 10)->default('POST')->after('ping_url');
      $table->json('ping_headers')->nullable()->after('ping_method');
      $table->text('ping_body')->nullable()->after('ping_headers');
      $table->string('post_url', 2048)->nullable()->after('ping_timeout_ms');
      $table->string('post_method', 10)->default('POST')->after('post_url');
      $table->json('post_headers')->nullable()->after('post_method');
      $table->text('post_body')->nullable()->after('post_headers');
    });
  }

  public function down(): void
  {
    Schema::table('buyer_configs', function (Blueprint $table): void {
      $table->dropColumn(['ping_url', 'ping_method', 'ping_headers', 'ping_body', 'post_url', 'post_method', 'post_headers', 'post_body']);
      $table->foreignId('ping_environment_id')->nullable()->constrained('integration_environments')->nullOnDelete();
      $table->foreignId('post_environment_id')->nullable()->constrained('integration_environments')->nullOnDelete();
    });
  }
};
