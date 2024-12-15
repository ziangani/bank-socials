<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('chat_user_logins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_user_id')->constrained('chat_users')->onDelete('cascade');
            $table->string('session_id');
            $table->string('phone_number');
            $table->timestamp('authenticated_at');
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['phone_number', 'is_active']);
            $table->index(['session_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_user_logins');
    }
};
