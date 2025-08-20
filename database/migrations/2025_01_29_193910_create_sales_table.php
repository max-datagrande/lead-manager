<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesTable extends Migration
{
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->integer('lead_id')->unsigned();
            $table->timestamp('sale_date');
            $table->decimal('price', 10, 2)->nullable()->unsigned();
            $table->integer('integration_id')->unsigned();
            $table->smallInteger('user_id')->nullable()->unsigned();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
}

return new CreateSalesTable;
