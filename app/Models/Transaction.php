<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'reference',
        'type',
        'amount',
        'sender',
        'recipient',
        'status',
        'fee',
        'channel',
        'currency',
        'metadata',
        'description',
        'reversal_reference',
        'processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fee' => 'decimal:2',
        'metadata' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the transaction logs for this transaction
     */
    public function logs()
    {
        return $this->hasMany(TransactionLog::class);
    }

    /**
     * Get the reversal transaction if this transaction was reversed
     */
    public function reversal()
    {
        return $this->hasOne(Transaction::class, 'reference', 'reversal_reference');
    }

    /**
     * Get the original transaction if this is a reversal
     */
    public function originalTransaction()
    {
        return $this->belongsTo(Transaction::class, 'reversal_reference', 'reference');
    }

    /**
     * Scope a query to only include transactions of a specific type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include transactions with a specific status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include transactions from a specific channel
     */
    public function scopeFromChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope a query to only include transactions within a date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope a query to only include transactions for a specific user (sender or recipient)
     */
    public function scopeForUser($query, $user)
    {
        return $query->where('sender', $user)
            ->orWhere('recipient', $user);
    }

    /**
     * Add a log entry for this transaction
     */
    public function addLog($status, $message, $metadata = null)
    {
        return $this->logs()->create([
            'status' => $status,
            'message' => $message,
            'metadata' => $metadata
        ]);
    }

    /**
     * Check if transaction can be reversed
     */
    public function canBeReversed(): bool
    {
        return $this->status === 'success' && !$this->reversal_reference;
    }

    /**
     * Get transaction fee details
     */
    public function getFeeDetails(): array
    {
        return [
            'amount' => $this->fee,
            'currency' => $this->currency,
            'type' => $this->type
        ];
    }

    /**
     * Get formatted amount with currency
     */
    public function getFormattedAmount(): string
    {
        return "{$this->currency} {$this->amount}";
    }
}
