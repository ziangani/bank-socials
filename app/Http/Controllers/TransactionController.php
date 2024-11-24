<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\TransactionService;
use App\Common\GeneralStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    protected TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Display a listing of transactions.
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->validate([
                'type' => 'string|nullable',
                'status' => 'string|nullable',
                'start_date' => 'date|nullable',
                'end_date' => 'date|nullable',
                'per_page' => 'integer|nullable|min:1|max:100'
            ]);

            $result = $this->transactionService->getHistory($filters);

            if ($result['status'] === GeneralStatus::SUCCESS) {
                return response()->json([
                    'status' => 'success',
                    'data' => $result['data']
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to fetch transactions: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch transactions'
            ], 500);
        }
    }

    /**
     * Store a newly created transaction.
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'type' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'sender' => 'required|string',
                'recipient' => 'required|string',
                'metadata' => 'array|nullable',
                'description' => 'string|nullable'
            ]);

            // Initialize transaction
            $result = $this->transactionService->initialize($data);
            if ($result['status'] !== GeneralStatus::SUCCESS) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], 400);
            }

            // Validate transaction
            $validationResult = $this->transactionService->validate($data);
            if ($validationResult['status'] !== GeneralStatus::SUCCESS) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validationResult['message']
                ], 400);
            }

            // Process transaction
            $processResult = $this->transactionService->process($data);
            if ($processResult['status'] !== GeneralStatus::SUCCESS) {
                return response()->json([
                    'status' => 'error',
                    'message' => $processResult['message']
                ], 400);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'reference' => $result['reference'],
                    'message' => 'Transaction processed successfully'
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create transaction: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create transaction'
            ], 500);
        }
    }

    /**
     * Display the specified transaction.
     */
    public function show(string $reference)
    {
        try {
            $result = $this->transactionService->verify($reference);

            if ($result['status'] === GeneralStatus::SUCCESS) {
                return response()->json([
                    'status' => 'success',
                    'data' => $result['data']
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $result['message']
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to fetch transaction: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch transaction'
            ], 500);
        }
    }

    /**
     * Get transaction status.
     */
    public function status(string $reference)
    {
        try {
            $result = $this->transactionService->verify($reference);

            if ($result['status'] === GeneralStatus::SUCCESS) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'reference' => $reference,
                        'status' => $result['data']['status']
                    ]
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $result['message']
            ], 404);

        } catch (\Exception $e) {
            Log::error('Failed to fetch transaction status: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch transaction status'
            ], 500);
        }
    }

    /**
     * Reverse a transaction.
     */
    public function reverse(string $reference)
    {
        try {
            $result = $this->transactionService->reverse($reference);

            if ($result['status'] === GeneralStatus::SUCCESS) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'reference' => $reference,
                        'message' => 'Transaction reversed successfully'
                    ]
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => $result['message']
            ], 400);

        } catch (\Exception $e) {
            Log::error('Failed to reverse transaction: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reverse transaction'
            ], 500);
        }
    }
}
