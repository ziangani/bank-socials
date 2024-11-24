<?php

namespace App\Services;

use App\Common\GeneralStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BaseService
{
    protected const OTP_PREFIX = 'otp_';
    protected const PIN_ATTEMPTS_PREFIX = 'pin_attempts_';
    protected const OTP_LENGTH = 6;
    protected const MAX_PIN_ATTEMPTS = 3;
    protected const PIN_TIMEOUT_MINUTES = 30;
    protected const OTP_EXPIRY_MINUTES = 10;

    /**
     * Generate and store OTP
     */
    protected function generateOTP(string $identifier): string
    {
        try {
            $otpLength = config('social-banking.security.otp_length', self::OTP_LENGTH);
            $otp = '';
            
            // Generate numeric OTP
            for ($i = 0; $i < $otpLength; $i++) {
                $otp .= mt_rand(0, 9);
            }

            // Store OTP with expiry
            $expiryMinutes = config('social-banking.security.otp_expiry', self::OTP_EXPIRY_MINUTES);
            Cache::put(
                $this->getOTPKey($identifier),
                $otp,
                now()->addMinutes($expiryMinutes)
            );

            return $otp;
        } catch (\Exception $e) {
            Log::error('OTP generation error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validate OTP
     */
    protected function validateOTP(string $identifier, string $otp): bool
    {
        try {
            $storedOTP = Cache::get($this->getOTPKey($identifier));
            
            if (!$storedOTP) {
                return false;
            }

            $isValid = $otp === $storedOTP;

            if ($isValid) {
                // Remove OTP after successful validation
                Cache::forget($this->getOTPKey($identifier));
            }

            return $isValid;
        } catch (\Exception $e) {
            Log::error('OTP validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate PIN
     */
    protected function validatePIN(string $identifier, string $pin): array
    {
        try {
            // Check PIN attempts
            $attempts = Cache::get($this->getPINAttemptsKey($identifier), 0);
            $maxAttempts = config('social-banking.security.max_pin_attempts', self::MAX_PIN_ATTEMPTS);

            if ($attempts >= $maxAttempts) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Maximum PIN attempts exceeded. Please try again later.'
                ];
            }

            // TODO: Implement actual PIN validation against secure storage
            $isValid = true; // Mock validation

            if (!$isValid) {
                // Increment attempts
                Cache::put(
                    $this->getPINAttemptsKey($identifier),
                    $attempts + 1,
                    now()->addMinutes(config('social-banking.security.pin_timeout', self::PIN_TIMEOUT_MINUTES))
                );

                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Invalid PIN. ' . ($maxAttempts - ($attempts + 1)) . ' attempts remaining.'
                ];
            }

            // Reset attempts on successful validation
            Cache::forget($this->getPINAttemptsKey($identifier));

            return [
                'status' => GeneralStatus::SUCCESS,
                'message' => 'PIN validated successfully'
            ];
        } catch (\Exception $e) {
            Log::error('PIN validation error: ' . $e->getMessage());
            return [
                'status' => GeneralStatus::ERROR,
                'message' => 'Error validating PIN'
            ];
        }
    }

    /**
     * Reset PIN attempts
     */
    protected function resetPINAttempts(string $identifier): bool
    {
        try {
            return Cache::forget($this->getPINAttemptsKey($identifier));
        } catch (\Exception $e) {
            Log::error('PIN attempts reset error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate reference number
     */
    protected function generateReference(string $prefix = ''): string
    {
        return strtoupper($prefix . Str::random(12));
    }

    /**
     * Format amount with currency
     */
    protected function formatAmount(float $amount, string $currency = null): string
    {
        $currency = $currency ?? config('social-banking.transactions.default_currency', 'KES');
        return $currency . ' ' . number_format($amount, 2);
    }

    /**
     * Get OTP cache key
     */
    protected function getOTPKey(string $identifier): string
    {
        return self::OTP_PREFIX . $identifier;
    }

    /**
     * Get PIN attempts cache key
     */
    protected function getPINAttemptsKey(string $identifier): string
    {
        return self::PIN_ATTEMPTS_PREFIX . $identifier;
    }

    /**
     * Log error and return error response
     */
    protected function logAndReturnError(string $message, \Exception $e = null): array
    {
        if ($e) {
            Log::error($message . ': ' . $e->getMessage());
        } else {
            Log::error($message);
        }

        return [
            'status' => GeneralStatus::ERROR,
            'message' => $message
        ];
    }

    /**
     * Return success response
     */
    protected function returnSuccess(string $message, array $data = []): array
    {
        return array_merge([
            'status' => GeneralStatus::SUCCESS,
            'message' => $message
        ], $data);
    }

    /**
     * Validate phone number
     */
    protected function validatePhoneNumber(string $phone): bool
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Check if it's a valid Zambian number
        if (strlen($phone) === 10) {
            $phone = '26' . $phone;
        }

        return strlen($phone) === 12 && str_starts_with($phone, '26');
    }

    /**
     * Format phone number
     */
    protected function formatPhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Add country code if missing
        if (strlen($phone) === 10) {
            $phone = '26' . $phone;
        }

        return $phone;
    }

    /**
     * Validate amount
     */
    protected function validateAmount(float $amount): array
    {
        $minAmount = config('social-banking.transactions.min_amount', 10);
        $maxAmount = config('social-banking.transactions.max_amount', 150000);

        if ($amount < $minAmount) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => "Amount must be at least " . $this->formatAmount($minAmount)
            ];
        }

        if ($amount > $maxAmount) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => "Amount cannot exceed " . $this->formatAmount($maxAmount)
            ];
        }

        return [
            'status' => GeneralStatus::SUCCESS,
            'message' => 'Amount validated successfully'
        ];
    }
}
