<?php

namespace App\Services;

use App\Common\GeneralStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthenticationService extends BaseService
{
    /**
     * Handle card-based registration
     */
    public function registerWithCard(array $data): array
    {
        try {
            // Validate card details
            $cardValidation = $this->validateCardDetails($data);
            if ($cardValidation['status'] !== GeneralStatus::SUCCESS) {
                return $cardValidation;
            }

            // Generate and send OTP
            $otp = $this->generateOTP($data['phone_number']);
            $this->sendOTP($data['phone_number'], $otp);

            return $this->returnSuccess('OTP sent successfully', [
                'requires_otp' => true,
                'reference' => $this->generateReference('REG')
            ]);

        } catch (\Exception $e) {
            return $this->logAndReturnError('Card registration failed', $e);
        }
    }

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
     * Validate card details
     */
    protected function validateCardDetails(array $data): array
    {
        // Remove spaces and non-numeric characters
        $cardNumber = preg_replace('/\D/', '', $data['card_number']);

        if (strlen($cardNumber) !== 16) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => 'Invalid card number'
            ];
        }

        // Validate expiry date
        if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $data['expiry'])) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => 'Invalid expiry date'
            ];
        }

        // Validate CVV
        if (!preg_match('/^[0-9]{3}$/', $data['cvv'])) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => 'Invalid CVV'
            ];
        }

        // TODO: Implement actual card validation with payment provider

        return [
            'status' => GeneralStatus::SUCCESS,
            'message' => 'Card details validated successfully'
        ];
    }

    /**
     * Validate account details
     */
    protected function validateAccountDetails(array $data): array
    {
        // Validate account number format
        if (!preg_match('/^[0-9]{10,}$/', $data['account_number'])) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => 'Invalid account number'
            ];
        }

        // Validate ID number
        if (empty($data['id_number'])) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => 'ID number is required'
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
        $user->name = $data['name'];
        $user->phone_number = $this->formatPhoneNumber($data['phone_number']);
        $user->email = $data['email'] ?? null;
        $user->account_number = $data['account_number'] ?? null;
        $user->card_number = $data['card_number'] ?? null;
        $user->id_number = $data['id_number'] ?? null;
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
}
