<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationsTable extends Migration
{
    public function up()
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id')->unsigned();
            $table->string('name', 100);
            $table->string('method', 100);
            $table->boolean('environment')->default(0)->comment('0 = DEV, 1 = PROD');
            $table->text('test_url');
            $table->text('production_url');
            $table->text('request_body');
            $table->text('request_headers');
            $table->string('content_type');
            $table->string('authentication_type');
            $table->boolean('is_active')->default(1)->comment('0 = deactivated, 1 = activated');
            $table->smallInteger('user_id')->nullable()->unsigned();
            $table->smallInteger('updated_user_id')->nullable()->unsigned();
            $table->timestamps(); // Includes created_at and updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('integrations');
    }
}

return new CreateIntegrationsTable;
