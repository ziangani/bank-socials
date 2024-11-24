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
            $table->string('sender');
            $table->string('state')->default('INIT');
            $table->json('data')->nullable();
            $table->string('status')->default('active');
            $table->string('driver')->default('whatsapp'); // whatsapp or ussd
            $table->timestamps();

            // Indexes for quick lookups
            $table->index(['session_id', 'status']);
            $table->index(['sender', 'status']);
            $table->index('driver');
            $table->index('created_at');
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
