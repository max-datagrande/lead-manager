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
    Schema::create('postbacks', function (Blueprint $table) {
      $table->id();
      $table->string('vendor')->nullable(); // Para identificar el proveedor (ni, etc.)
      $table->string('clid')->nullable(); // Click ID - pub_param_1
      $table->decimal('payout', 10, 2)->nullable(); // Payout amount - pub_param_2
      $table->string('txid')->nullable(); // Transaction ID - OPTIONAL
      $table->string('currency', 3)->default('USD'); // Currency code
      $table->string('event')->nullable(); // Event type - OPTIONAL
      $table->string('offer_id'); // Offer ID - required
      $table->string('status')->default('pending'); // pending, processed, failed
      $table->text('response_data')->nullable(); // Para guardar la respuesta del postback
      $table->timestamp('processed_at')->nullable(); // Cuando se procesó
      $table->timestamps();

      // Índices para mejorar el rendimiento
      $table->index(['vendor', 'status']);
      $table->index(['offer_id']);
      $table->index(['clid']);
      $table->index(['created_at']);
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('postbacks');
  }
};
