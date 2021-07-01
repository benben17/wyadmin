<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateHelpWorkTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       Schema::create('help_work_orders', function (Blueprint $table) {
           $table->increments('id');
           $table->integer('p_id');
           $table->integer('user_id');
           $table->text('content');
           $table->enum('role', ['user', 'admin'])->default('user');
           $table->json('images')->nullable();
           $table->boolean('is_close')->default(false);
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
        Schema::dropIfExists('_help_work');
    }
}
