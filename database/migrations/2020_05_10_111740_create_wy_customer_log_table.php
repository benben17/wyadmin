<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateWyCustomerLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('wy_bse_customer_log', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->string('content',500);
			$table->integer('customer_id');
            $table->integer('c_uid');
            $table->string('create_person',10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('wy_customer_log');
    }
}
