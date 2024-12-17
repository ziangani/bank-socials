<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('chat_users', function (Blueprint $table) {
            $table->string('account_class')->default('standard')->after('pin');
        });
    }

    public function down()
    {
        Schema::table('chat_users', function (Blueprint $table) {
            $table->dropColumn('account_class');
        });
    }
};
