<?php

namespace App\Services;

use App\Common\GeneralStatus;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillPaymentService extends BaseService
{
    /**
     * Validate bill account
     */
    public function validateBillAccount(string $accountNumber, string $billType): array
    {
        try {
            // TODO: Implement actual bill validation with provider
            $billInfo = $this->getBillInformation($accountNumber, $billType);

            if (!$billInfo) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Invalid account number or no bills found'
                ];
            }

            return $this->returnSuccess('Bill account validated', [
                'account_name' => $billInfo['account_name'],
                'account_number' => $accountNumber,
                'bill_type' => $billType,
                'bills' => $billInfo['bills']
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Bill validation failed', $e);
        }
    }

    /**
     * Process bill payment
     */
    public function processBillPayment(array $data): array
    {
        try {
            DB::beginTransaction();

            // Validate amount
            $amountValidation = $this->validateAmount($data['amount']);
            if ($amountValidation['status'] !== GeneralStatus::SUCCESS) {
                return $amountValidation;
            }

            // Validate bill account
            $billValidation = $this->validateBillAccount($data['bill_account'], $data['bill_type']);
            if ($billValidation['status'] !== GeneralStatus::SUCCESS) {
                return $billValidation;
            }

            // Calculate fees
            $fees = $this->calculateBillPaymentFees($data['amount']);
            $totalAmount = $data['amount'] + $fees['total'];

            // Check balance
            $balanceCheck = $this->checkBalance($data['payer'], $totalAmount);
            if ($balanceCheck['status'] !== GeneralStatus::SUCCESS) {
                return $balanceCheck;
            }

            // Process payment
            $reference = $this->generateReference('BIL');
            $transaction = $this->createBillTransaction($data, $reference, $fees);

            // TODO: Implement actual bill payment with provider

            DB::commit();

            return $this->returnSuccess('Bill payment successful', [
                'reference' => $reference,
                'amount' => $this->formatAmount($data['amount']),
                'fees' => $this->formatAmount($fees['total']),
                'total' => $this->formatAmount($totalAmount),
                'bill_account' => $data['bill_account'],
                'bill_type' => $data['bill_type']
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->logAndReturnError('Bill payment failed', $e);
        }
    }

    /**
     * Check bill payment status
     */
    public function checkPaymentStatus(string $reference): array
    {
        try {
            $transaction = Transaction::where('reference', $reference)
                ->where('type', 'bill_payment')
                ->first();
            
            if (!$transaction) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Bill payment not found'
                ];
            }

            return $this->returnSuccess('Payment status retrieved', [
                'reference' => $transaction->reference,
                'status' => $transaction->status,
                'amount' => $this->formatAmount($transaction->amount),
                'bill_type' => $transaction->metadata['bill_type'],
                'bill_account' => $transaction->metadata['bill_account'],
                'created_at' => $transaction->created_at->format('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Failed to check payment status', $e);
        }
    }

    /**
     * Get bill payment history
     */
    public function getPaymentHistory(string $accountNumber, string $billType = null): array
    {
        try {
            $query = Transaction::where('type', 'bill_payment')
                ->where(function ($q) use ($accountNumber) {
                    $q->where('sender', $accountNumber)
                        ->orWhereJsonContains('metadata->bill_account', $accountNumber);
                })
                ->orderBy('created_at', 'desc')
                ->limit(10);

            if ($billType) {
                $query->whereJsonContains('metadata->bill_type', $billType);
            }

            $transactions = $query->get();

            $history = $transactions->map(function ($transaction) {
                return [
                    'reference' => $transaction->reference,
                    'amount' => $this->formatAmount($transaction->amount),
                    'status' => $transaction->status,
                    'bill_type' => $transaction->metadata['bill_type'],
                    'bill_account' => $transaction->metadata['bill_account'],
                    'date' => $transaction->created_at->format('Y-m-d H:i:s')
                ];
            });

            return $this->returnSuccess('Payment history retrieved', [
                'history' => $history
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Failed to retrieve payment history', $e);
        }
    }

    /**
     * Get bill information
     */
    protected function getBillInformation(string $accountNumber, string $billType): ?array
    {
        // TODO: Implement actual bill information retrieval from provider
        return [
            'account_name' => 'John Doe',
            'account_number' => $accountNumber,
            'bills' => [
                [
                    'id' => 'BILL001',
                    'description' => 'Water Bill - March 2024',
                    'amount' => 150.00,
                    'due_date' => '2024-03-31'
                ],
                [
                    'id' => 'BILL002',
                    'description' => 'Water Bill - February 2024',
                    'amount' => 120.00,
                    'due_date' => '2024-02-29'
                ]
            ]
        ];
    }

    /**
     * Calculate bill payment fees
     */
    protected function calculateBillPaymentFees(float $amount): array
    {
        $fixedFee = config('social-banking.fees.bill.fixed', 30);
        $percentageFee = $amount * (config('social-banking.fees.bill.percentage', 0) / 100);

        return [
            'fixed' => $fixedFee,
            'percentage' => $percentageFee,
            'total' => $fixedFee + $percentageFee
        ];
    }

    /**
     * Check account balance
     */
    protected function checkBalance(string $accountNumber, float $amount): array
    {
        // TODO: Implement actual balance check with core banking system
        return [
            'status' => GeneralStatus::SUCCESS,
            'message' => 'Sufficient balance'
        ];
    }

    /**
     * Create bill transaction record
     */
    protected function createBillTransaction(array $data, string $reference, array $fees): Transaction
    {
        $transaction = new Transaction();
        $transaction->reference = $reference;
        $transaction->type = 'bill_payment';
        $transaction->amount = $data['amount'];
        $transaction->sender = $data['payer'];
        $transaction->recipient = $data['bill_account'];
        $transaction->status = 'pending';
        $transaction->fee = $fees['total'];
        $transaction->metadata = [
            'bill_type' => $data['bill_type'],
            'bill_account' => $data['bill_account'],
            'bill_reference' => $data['bill_reference'] ?? null,
            'fees' => $fees
        ];
        $transaction->save();

        return $transaction;
    }
}
