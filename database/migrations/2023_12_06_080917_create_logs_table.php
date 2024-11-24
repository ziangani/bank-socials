<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('entity_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('action')->nullable();
            $table->string('description')->nullable();
            $table->string('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->json('old_state')->nullable();
            $table->json('new_state')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('locale')->nullable();
            $table->string('remarks')->nullable();
            $table->text('data1')->nullable();
            $table->text('data2')->nullable();
            $table->text('data3')->nullable();
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
        Schema::dropIfExists('logs');
    }
}
