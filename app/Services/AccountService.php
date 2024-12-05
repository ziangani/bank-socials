<?php

namespace App\Services;

use App\Common\GeneralStatus;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AccountService extends BaseService
{
    /**
     * Get account balance
     */
    public function getBalance(string $accountNumber, string $pin): array
    {
        try {
            // Validate PIN
            $pinValidation = $this->validatePIN($accountNumber, $pin);
            if ($pinValidation['status'] !== GeneralStatus::SUCCESS) {
                return $pinValidation;
            }

            // TODO: Implement actual balance fetch from core banking system
            $balanceInfo = $this->fetchBalanceInformation($accountNumber);

            return $this->returnSuccess('Balance retrieved successfully', [
                'available_balance' => $this->formatAmount($balanceInfo['available']),
                'current_balance' => $this->formatAmount($balanceInfo['current']),
                'hold_amount' => $this->formatAmount($balanceInfo['hold']),
                'currency' => $balanceInfo['currency']
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Failed to retrieve balance', $e);
        }
    }

    /**
     * Get mini statement
     */
    public function getMiniStatement(string $accountNumber, string $pin): array
    {
        try {
            // Validate PIN
            $pinValidation = $this->validatePIN($accountNumber, $pin);
            if ($pinValidation['status'] !== GeneralStatus::SUCCESS) {
                return $pinValidation;
            }

            $transactions = Transaction::where(function ($query) use ($accountNumber) {
                $query->where('sender', $accountNumber)
                    ->orWhere('recipient', $accountNumber);
            })
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

            $statement = $transactions->map(function ($transaction) use ($accountNumber) {
                $isDebit = $transaction->sender === $accountNumber;
                return [
                    'date' => $transaction->created_at->format('Y-m-d H:i:s'),
                    'description' => $this->getTransactionDescription($transaction),
                    'amount' => $this->formatAmount($transaction->amount),
                    'type' => $isDebit ? 'DEBIT' : 'CREDIT',
                    'reference' => $transaction->reference
                ];
            });

            return $this->returnSuccess('Mini statement retrieved', [
                'transactions' => $statement
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Failed to retrieve mini statement', $e);
        }
    }

    /**
     * Get full statement
     */
    public function getFullStatement(string $accountNumber, string $pin, array $filters = []): array
    {
        try {
            // Validate PIN
            $pinValidation = $this->validatePIN($accountNumber, $pin);
            if ($pinValidation['status'] !== GeneralStatus::SUCCESS) {
                return $pinValidation;
            }

            $query = Transaction::where(function ($query) use ($accountNumber) {
                $query->where('sender', $accountNumber)
                    ->orWhere('recipient', $accountNumber);
            })
            ->orderBy('created_at', 'desc');

            // Apply filters
            if (isset($filters['start_date'])) {
                $query->where('created_at', '>=', $filters['start_date']);
            }
            if (isset($filters['end_date'])) {
                $query->where('created_at', '<=', $filters['end_date']);
            }
            if (isset($filters['type'])) {
                $query->where('type', $filters['type']);
            }
            if (isset($filters['min_amount'])) {
                $query->where('amount', '>=', $filters['min_amount']);
            }
            if (isset($filters['max_amount'])) {
                $query->where('amount', '<=', $filters['max_amount']);
            }

            $transactions = $query->paginate($filters['per_page'] ?? 20);

            $statement = $transactions->map(function ($transaction) use ($accountNumber) {
                $isDebit = $transaction->sender === $accountNumber;
                return [
                    'date' => $transaction->created_at->format('Y-m-d H:i:s'),
                    'description' => $this->getTransactionDescription($transaction),
                    'amount' => $this->formatAmount($transaction->amount),
                    'type' => $isDebit ? 'DEBIT' : 'CREDIT',
                    'reference' => $transaction->reference,
                    'status' => $transaction->status,
                    'fee' => $this->formatAmount($transaction->fee),
                    'metadata' => $transaction->metadata
                ];
            });

            return $this->returnSuccess('Full statement retrieved', [
                'transactions' => $statement,
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'total_pages' => $transactions->lastPage(),
                    'total_records' => $transactions->total(),
                    'per_page' => $transactions->perPage()
                ]
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Failed to retrieve full statement', $e);
        }
    }

    /**
     * Get account limits
     */
    public function getAccountLimits(string $accountNumber): array
    {
        try {
            $user = User::where('account_number', $accountNumber)->first();
            if (!$user) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Account not found'
                ];
            }

            $userClass = $user->account_class ?? 'standard';
            $limits = config("social-banking.transactions.limits.{$userClass}", [
                'daily' => 300000,
                'single' => 100000,
                'monthly' => 1000000
            ]);

            return $this->returnSuccess('Account limits retrieved', [
                'account_class' => $userClass,
                'limits' => [
                    'single_transaction' => $this->formatAmount($limits['single']),
                    'daily' => $this->formatAmount($limits['daily']),
                    'monthly' => $this->formatAmount($limits['monthly'])
                ],
                'usage' => [
                    'daily' => $this->getDailyUsage($accountNumber),
                    'monthly' => $this->getMonthlyUsage($accountNumber)
                ]
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Failed to retrieve account limits', $e);
        }
    }

    /**
     * Get account profile
     */
    public function getAccountProfile(string $accountNumber): array
    {
        try {
            $user = User::where('account_number', $accountNumber)->first();
            if (!$user) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Account not found'
                ];
            }

            return $this->returnSuccess('Account profile retrieved', [
                'name' => $user->name,
                'account_number' => $user->account_number,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'account_class' => $user->account_class ?? 'standard',
                'status' => $user->status,
                'created_at' => $user->created_at->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Failed to retrieve account profile', $e);
        }
    }

    /**
     * Fetch balance information
     */
    protected function fetchBalanceInformation(string $accountNumber): array
    {
        // TODO: Implement actual balance fetch from core banking system
        return [
            'available' => 5000.00,
            'current' => 5000.00,
            'hold' => 0.00,
            'currency' => config('social-banking.transactions.default_currency')
        ];
    }

    /**
     * Get transaction description
     */
    protected function getTransactionDescription(Transaction $transaction): string
    {
        return match($transaction->type) {
            'internal' => "Transfer to " . $this->maskAccountNumber($transaction->recipient),
            'bank' => "Bank transfer to " . ($transaction->metadata['bank_details']['bank_name'] ?? 'Unknown Bank'),
            'mobile_money' => "Mobile money transfer to " . $this->maskPhoneNumber($transaction->recipient),
            'bill_payment' => "Bill payment - " . ($transaction->metadata['bill_type'] ?? 'Unknown Bill'),
            default => $transaction->type
        };
    }

    /**
     * Get daily usage
     */
    protected function getDailyUsage(string $accountNumber): array
    {
        $today = now()->startOfDay();
        $amount = Transaction::where('sender', $accountNumber)
            ->where('created_at', '>=', $today)
            ->where('status', 'success')
            ->sum('amount');

        return [
            'amount' => $this->formatAmount($amount),
            'date' => $today->format('Y-m-d')
        ];
    }

    /**
     * Get monthly usage
     */
    protected function getMonthlyUsage(string $accountNumber): array
    {
        $startOfMonth = now()->startOfMonth();
        $amount = Transaction::where('sender', $accountNumber)
            ->where('created_at', '>=', $startOfMonth)
            ->where('status', 'success')
            ->sum('amount');

        return [
            'amount' => $this->formatAmount($amount),
            'month' => $startOfMonth->format('Y-m')
        ];
    }

    /**
     * Mask account number
     */
    protected function maskAccountNumber(string $accountNumber): string
    {
        $length = strlen($accountNumber);
        $visibleCount = 4;
        $hiddenCount = $length - $visibleCount;
        return str_repeat('*', $hiddenCount) . substr($accountNumber, -$visibleCount);
    }

    /**
     * Mask phone number
     */
    protected function maskPhoneNumber(string $phoneNumber): string
    {
        return substr($phoneNumber, 0, 4) . '****' . substr($phoneNumber, -4);
    }
}
