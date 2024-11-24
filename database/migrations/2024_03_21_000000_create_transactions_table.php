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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->string('sender');
            $table->string('recipient');
            $table->string('status');
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('channel')->nullable();
            $table->string('currency')->default('KES');
            $table->json('metadata')->nullable();
            $table->string('description')->nullable();
            $table->string('reversal_reference')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('reference');
            $table->index('sender');
            $table->index('recipient');
            $table->index('status');
            $table->index('type');
            $table->index('channel');
            $table->index(['created_at', 'status']);
        });

        // Create transaction_logs table for detailed transaction history
        Schema::create('transaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions');
            $table->string('status');
            $table->string('message');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('transaction_id');
            $table->index('status');
            $table->index('created_at');
        });

        // Create transaction_limits table
        Schema::create('transaction_limits', function (Blueprint $table) {
            $table->id();
            $table->string('user_class');
            $table->string('transaction_type');
            $table->decimal('single_limit', 15, 2);
            $table->decimal('daily_limit', 15, 2);
            $table->decimal('monthly_limit', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique(['user_class', 'transaction_type']);
            $table->index('user_class');
            $table->index('transaction_type');
            $table->index('is_active');
        });

        // Create transaction_fees table
        Schema::create('transaction_fees', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type');
            $table->decimal('min_amount', 15, 2);
            $table->decimal('max_amount', 15, 2);
            $table->decimal('fixed_fee', 10, 2)->default(0);
            $table->decimal('percentage_fee', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('transaction_type');
            $table->index('is_active');
            $table->index(['min_amount', 'max_amount']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_fees');
        Schema::dropIfExists('transaction_limits');
        Schema::dropIfExists('transaction_logs');
        Schema::dropIfExists('transactions');
    }
};
