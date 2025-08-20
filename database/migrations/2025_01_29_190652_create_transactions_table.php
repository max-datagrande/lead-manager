<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('sale_id')->unsigned();
            $table->timestamp('transaction_date');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('status')->default(1)->comment('1 = pending, 2 = approved, 3 = declined');
            $table->json('transaction_details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
}

return new CreateTransactionsTable;
