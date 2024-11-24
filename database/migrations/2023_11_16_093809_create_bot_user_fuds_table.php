<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBotUserFudsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bot_user_fuds', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->string('system_value');
            $table->string('friendly_value');
            $table->string('source');
            $table->string('module');
            $table->string('type');
            $table->softDeletes();
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
        Schema::dropIfExists('bot_user_fuds');
    }
}
