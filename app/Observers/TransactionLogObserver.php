<?php

namespace App\Observers;

use App\Models\TransactionLog;
use Illuminate\Support\Facades\Log;

class TransactionLogObserver
{
    /**
     * Handle the TransactionLog "created" event.
     */
    public function created(TransactionLog $log): void
    {
        Log::info('Transaction log created', [
            'transaction_id' => $log->transaction_id,
            'status' => $log->status,
            'message' => $log->message
        ]);
    }

    /**
     * Handle the TransactionLog "updated" event.
     */
    public function updated(TransactionLog $log): void
    {
        if ($log->isDirty('status')) {
            Log::info('Transaction log status updated', [
                'transaction_id' => $log->transaction_id,
                'old_status' => $log->getOriginal('status'),
                'new_status' => $log->status
            ]);
        }
    }

    /**
     * Handle the TransactionLog "deleted" event.
     */
    public function deleted(TransactionLog $log): void
    {
        Log::info('Transaction log deleted', [
            'transaction_id' => $log->transaction_id,
            'status' => $log->status
        ]);
    }

    /**
     * Handle the TransactionLog "restored" event.
     */
    public function restored(TransactionLog $log): void
    {
        Log::info('Transaction log restored', [
            'transaction_id' => $log->transaction_id,
            'status' => $log->status
        ]);
    }

    /**
     * Handle the TransactionLog "force deleted" event.
     */
    public function forceDeleted(TransactionLog $log): void
    {
        Log::info('Transaction log force deleted', [
            'transaction_id' => $log->transaction_id
        ]);
    }
}
