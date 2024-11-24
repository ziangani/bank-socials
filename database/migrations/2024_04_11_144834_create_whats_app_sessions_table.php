<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whats_app_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id');
            $table->string('mobile');
            $table->string('function');
            $table->string('action');
            $table->json('session_data');
            $table->string('request_reference');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whats_app_sessions');
    }
};
