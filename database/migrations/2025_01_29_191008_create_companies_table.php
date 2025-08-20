<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompaniesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('contact_email', 100)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('company_name')->nullable()->unique();
            $table->boolean('active')->default(1)->comment('0 = deactivated, 1 = activated');
            $table->smallInteger('user_id')->nullable()->unsigned();
            $table->smallInteger('updated_user_id')->nullable()->unsigned();
            $table->timestamps(); // Includes created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
}

return new CreateCompaniesTable;
