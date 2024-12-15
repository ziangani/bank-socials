<?php

namespace App\Services;

use App\Common\GeneralStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthenticationService extends BaseService
{
    /**
     * Handle account-based registration
     */
    public function registerWithAccount(array $data): array
    {
        try {
            // Validate account details
            $accountValidation = $this->validateAccountDetails($data);
            if ($accountValidation['status'] !== GeneralStatus::SUCCESS) {
                return $accountValidation;
            }

            // Generate and send OTP
            $otp = $this->generateOTP($data['phone_number']);
            $this->sendOTP($data['phone_number'], $otp);

            return $this->returnSuccess('OTP sent successfully', [
                'requires_otp' => true,
                'reference' => $this->generateReference('REG')
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Account registration failed', $e);
        }
    }

    /**
     * Verify OTP and complete registration
     */
    public function verifyRegistrationOTP(string $reference, string $otp, array $data): array
    {
        try {
            // Validate OTP
            if (!$this->validateOTP($data['phone_number'], $otp)) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Invalid OTP'
                ];
            }

            // Create user account
            $user = $this->createUserAccount($data);

            return $this->returnSuccess('Registration successful', [
                'user_id' => $user->id,
                'requires_pin_setup' => true
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Registration verification failed', $e);
        }
    }

    /**
     * Set up transaction PIN
     */
    public function setupPIN(string $userId, string $pin): array
    {
        try {
            // Validate PIN format
            if (!$this->validatePINFormat($pin)) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Invalid PIN format. PIN must be 4 digits.'
                ];
            }

            // Update user's PIN
            $user = User::find($userId);
            $user->transaction_pin = Hash::make($pin);
            $user->save();

            return $this->returnSuccess('PIN setup successful');

        } catch (\Exception $e) {
            return $this->logAndReturnError('PIN setup failed', $e);
        }
    }

    /**
     * Change transaction PIN
     */
    public function changePIN(string $userId, string $oldPin, string $newPin): array
    {
        try {
            $user = User::find($userId);

            // Validate old PIN
            if (!Hash::check($oldPin, $user->transaction_pin)) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Invalid current PIN'
                ];
            }

            // Validate new PIN format
            if (!$this->validatePINFormat($newPin)) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Invalid PIN format. PIN must be 4 digits.'
                ];
            }

            // Update PIN
            $user->transaction_pin = Hash::make($newPin);
            $user->save();

            return $this->returnSuccess('PIN changed successfully');

        } catch (\Exception $e) {
            return $this->logAndReturnError('PIN change failed', $e);
        }
    }

    /**
     * Reset PIN using OTP
     */
    public function resetPIN(string $userId): array
    {
        try {
            $user = User::find($userId);

            // Generate and send OTP
            $otp = $this->generateOTP($user->phone_number);
            $this->sendOTP($user->phone_number, $otp);

            return $this->returnSuccess('OTP sent successfully', [
                'requires_otp' => true,
                'reference' => $this->generateReference('PIN')
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('PIN reset initiation failed', $e);
        }
    }

    /**
     * Verify PIN reset OTP and set new PIN
     */
    public function verifyPINResetOTP(string $reference, string $otp, string $userId, string $newPin): array
    {
        try {
            $user = User::find($userId);

            // Validate OTP
            if (!$this->validateOTP($user->phone_number, $otp)) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Invalid OTP'
                ];
            }

            // Validate new PIN format
            if (!$this->validatePINFormat($newPin)) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Invalid PIN format. PIN must be 4 digits.'
                ];
            }

            // Update PIN
            $user->transaction_pin = Hash::make($newPin);
            $user->save();

            return $this->returnSuccess('PIN reset successful');

        } catch (\Exception $e) {
            return $this->logAndReturnError('PIN reset verification failed', $e);
        }
    }

    /**
     * Validate account details
     */
    public function validateAccountDetails(array $data): array
    {
        // Validate account number format
        if (!preg_match('/^[0-9]{10,}$/', $data['account_number'])) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => 'Invalid account number'
            ];
        }

        // Validate phone number
        if (empty($data['phone_number'])) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => 'Phone number is required'
            ];
        }

        // TODO: Implement actual account validation with core banking system

        return [
            'status' => GeneralStatus::SUCCESS,
            'message' => 'Account details validated successfully'
        ];
    }

    /**
     * Create user account
     */
    protected function createUserAccount(array $data): User
    {
        $user = new User();
        $user->name = $data['name'] ?? 'User';
        $user->phone_number = $this->formatPhoneNumber($data['phone_number']);
        $user->email = $data['email'] ?? null;
        $user->account_number = $data['account_number'];
        $user->status = 'active';
        $user->save();

        return $user;
    }

    /**
     * Send OTP to user
     */
    protected function sendOTP(string $phoneNumber, string $otp): void
    {
        // TODO: Implement actual OTP sending logic
        Log::info("OTP sent to $phoneNumber: $otp");
    }

    /**
     * Validate PIN format
     */
    protected function validatePINFormat(string $pin): bool
    {
        return preg_match('/^[0-9]{4}$/', $pin);
    }

    /**
     * Generate OTP
     */
    protected function generateOTP(string $phoneNumber): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Validate OTP
     */
    protected function validateOTP(string $phoneNumber, string $otp): bool
    {
        // TODO: Implement actual OTP validation
        return true;
    }

    /**
     * Format phone number
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Ensure it starts with country code
        if (strlen($cleaned) === 9) {
            return '254' . $cleaned;
        }
        if (strlen($cleaned) === 10 && $cleaned[0] === '0') {
            return '254' . substr($cleaned, 1);
        }
        return $cleaned;
    }
}
