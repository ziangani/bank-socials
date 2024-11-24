<?php

namespace App\Interfaces;

interface TransactionInterface
{
    /**
     * Initialize a transaction
     */
    public function initialize(array $data): array;

    /**
     * Validate transaction details
     */
    public function validate(array $data): array;

    /**
     * Process the transaction
     */
    public function process(array $data): array;

    /**
     * Verify transaction status
     */
    public function verify(string $reference): array;

    /**
     * Reverse/rollback transaction
     */
    public function reverse(string $reference): array;

    /**
     * Get transaction limits
     */
    public function getLimits(string $type, string $userClass): array;

    /**
     * Check if transaction exceeds limits
     */
    public function checkLimits(array $data): bool;

    /**
     * Get transaction fees
     */
    public function getFees(array $data): array;

    /**
     * Log transaction
     */
    public function log(array $data): bool;

    /**
     * Get transaction history
     */
    public function getHistory(array $filters): array;

    /**
     * Generate transaction reference
     */
    public function generateReference(): string;
}
