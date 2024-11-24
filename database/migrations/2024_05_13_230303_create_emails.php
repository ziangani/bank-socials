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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->string('from');
            $table->string('email');
            $table->text('message');
            $table->string('view');
            $table->text('response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->string('status')->default('PENDING');
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
