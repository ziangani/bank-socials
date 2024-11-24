<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_class',
        'transaction_type',
        'single_limit',
        'daily_limit',
        'monthly_limit',
        'is_active'
    ];

    protected $casts = [
        'single_limit' => 'decimal:2',
        'daily_limit' => 'decimal:2',
        'monthly_limit' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Scope a query to only include active limits
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include limits for a specific user class
     */
    public function scopeForUserClass($query, $userClass)
    {
        return $query->where('user_class', $userClass);
    }

    /**
     * Scope a query to only include limits for a specific transaction type
     */
    public function scopeForTransactionType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Check if a transaction amount is within limits
     */
    public function isWithinLimits($amount): bool
    {
        return $amount <= $this->single_limit;
    }

    /**
     * Get formatted limits with currency
     */
    public function getFormattedLimits(): array
    {
        return [
            'single' => "KES {$this->single_limit}",
            'daily' => "KES {$this->daily_limit}",
            'monthly' => "KES {$this->monthly_limit}"
        ];
    }

    /**
     * Check if limit is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
