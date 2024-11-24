<?php

namespace App\Observers;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        // Log transaction creation
        Log::info('Transaction created', [
            'reference' => $transaction->reference,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
            'status' => $transaction->status
        ]);

        // Create initial transaction log
        $transaction->addLog(
            $transaction->status,
            'Transaction initiated',
            ['initial_status' => $transaction->status]
        );
    }

    /**
     * Handle the Transaction "updated" event.
     */
    public function updated(Transaction $transaction): void
    {
        // Log status changes
        if ($transaction->isDirty('status')) {
            $oldStatus = $transaction->getOriginal('status');
            $newStatus = $transaction->status;

            Log::info('Transaction status changed', [
                'reference' => $transaction->reference,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ]);

            // Add status change to transaction logs
            $transaction->addLog(
                $newStatus,
                "Status changed from {$oldStatus} to {$newStatus}",
                [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus
                ]
            );
        }
    }

    /**
     * Handle the Transaction "deleted" event.
     */
    public function deleted(Transaction $transaction): void
    {
        Log::info('Transaction deleted', [
            'reference' => $transaction->reference
        ]);

        // Add deletion log
        $transaction->addLog(
            'deleted',
            'Transaction deleted',
            ['deleted_at' => now()]
        );
    }

    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        Log::info('Transaction restored', [
            'reference' => $transaction->reference
        ]);

        // Add restoration log
        $transaction->addLog(
            'restored',
            'Transaction restored',
            ['restored_at' => now()]
        );
    }

    /**
     * Handle the Transaction "force deleted" event.
     */
    public function forceDeleted(Transaction $transaction): void
    {
        Log::info('Transaction force deleted', [
            'reference' => $transaction->reference
        ]);
    }
}
