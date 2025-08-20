<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('traffic_logs', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID as primary key
            $table->char('fingerprint', 64)->unique()->comment('IP + UserAgent + Date (YYYYY-MM-DD)');
            $table->date('visit_date');
            $table->unsignedInteger('visit_count')->default(1)->comment('Daily hit counter');

            // Application fields
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->text('referrer')->nullable();
            $table->string('host')->comment('Landing domain');
            $table->string('path_visited')->nullable();
            $table->json('query_params')->nullable();

            // URL parameters (s1-s4)
            $table->string('s1', 100)->nullable();
            $table->string('s2', 100)->nullable();
            $table->string('s3', 100)->nullable();
            $table->string('s4', 100)->nullable();

            // Traffic origin
            $table->enum('traffic_source', [
                'organic',
                'google',
                'facebook',
                'instagram',
                'email',
                'direct',
                'other',
            ])->nullable();

            // Geolocation
            $table->char('country_code', 2)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code', 100)->nullable();

            // Technical metadata
            $table->boolean('is_bot')->default(0)->comment('0 = false, 1 = true');

            // Timestamps
            $table->timestamps();
            // Simple index
            $table->index('created_at');
            $table->index('visit_date');
            $table->index('fingerprint');
            // Advanced index
            $table->index(['state', 'is_bot']);
            $table->index(['fingerprint', 'visit_date']);
            $table->index(['traffic_source', 'visit_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('traffic_logs');
    }
};
