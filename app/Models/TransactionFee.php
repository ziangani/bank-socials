<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransactionFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_type',
        'min_amount',
        'max_amount',
        'fixed_fee',
        'percentage_fee',
        'is_active'
    ];

    protected $casts = [
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'fixed_fee' => 'decimal:2',
        'percentage_fee' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Scope a query to only include active fees
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include fees for a specific transaction type
     */
    public function scopeForTransactionType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    /**
     * Scope a query to only include fees applicable for an amount
     */
    public function scopeForAmount($query, $amount)
    {
        return $query->where('min_amount', '<=', $amount)
            ->where('max_amount', '>=', $amount);
    }

    /**
     * Calculate fee for a given amount
     */
    public function calculateFee($amount): float
    {
        if ($amount < $this->min_amount || $amount > $this->max_amount) {
            return 0;
        }

        $percentageFee = ($amount * $this->percentage_fee) / 100;
        return $this->fixed_fee + $percentageFee;
    }

    /**
     * Get fee breakdown for a given amount
     */
    public function getFeeBreakdown($amount): array
    {
        $percentageFee = ($amount * $this->percentage_fee) / 100;

        return [
            'fixed_fee' => $this->fixed_fee,
            'percentage_fee' => $percentageFee,
            'total_fee' => $this->fixed_fee + $percentageFee,
            'percentage_rate' => $this->percentage_fee . '%',
            'amount' => $amount
        ];
    }

    /**
     * Check if fee is applicable for amount
     */
    public function isApplicableFor($amount): bool
    {
        return $amount >= $this->min_amount && 
               $amount <= $this->max_amount && 
               $this->is_active;
    }

    /**
     * Get formatted fee range
     */
    public function getFormattedRange(): string
    {
        return "KES {$this->min_amount} - KES {$this->max_amount}";
    }

    /**
     * Get formatted fee structure
     */
    public function getFormattedStructure(): string
    {
        $parts = [];
        
        if ($this->fixed_fee > 0) {
            $parts[] = "Fixed: KES {$this->fixed_fee}";
        }
        
        if ($this->percentage_fee > 0) {
            $parts[] = "Rate: {$this->percentage_fee}%";
        }

        return implode(' + ', $parts);
    }
}
