<?php

namespace App\Services;

use App\Common\GeneralStatus;
use App\Models\ChatUser;
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

            // Return success with reference
            $reference = $this->generateReference('REG');
            return [
                'status' => GeneralStatus::SUCCESS,
                'message' => 'OTP sent successfully',
                'data' => [
                    'requires_otp' => true,
                    'reference' => $reference,
                    'otp' => config('app.debug') ? $otp : null // Include OTP in debug mode only
                ]
            ];

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
                if (!config(('app.debug'))) {
                    return [
                        'status' => GeneralStatus::ERROR,
                        'message' => 'Invalid OTP'
                    ];
                }
            }

            // Create chat user account
            $user = $this->createChatUserAccount($data);

            return [
                'status' => GeneralStatus::SUCCESS,
                'message' => 'Registration successful',
                'data' => [
                    'user_id' => $user->id,
                    'requires_pin_setup' => isset($data['pin'])
                ]
            ];

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
            $user = ChatUser::find($userId);
            $user->pin = Hash::make($pin);
            $user->save();

            return [
                'status' => GeneralStatus::SUCCESS,
                'message' => 'PIN setup successful'
            ];

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
            $user = ChatUser::find($userId);

            // Validate old PIN
            if (!Hash::check($oldPin, $user->pin)) {
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
            $user->pin = Hash::make($newPin);
            $user->save();

            return [
                'status' => GeneralStatus::SUCCESS,
                'message' => 'PIN changed successfully'
            ];

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
            $user = ChatUser::find($userId);

            // Generate and send OTP
            $otp = $this->generateOTP($user->phone_number);
            $this->sendOTP($user->phone_number, $otp);

            // Generate reference
            $reference = $this->generateReference('PIN');
            return [
                'status' => GeneralStatus::SUCCESS,
                'message' => 'OTP sent successfully',
                'data' => [
                    'requires_otp' => true,
                    'reference' => $reference,
                    'otp' => config('app.debug') ? $otp : null // Include OTP in debug mode only
                ]
            ];

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
            $user = ChatUser::find($userId);

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
            $user->pin = Hash::make($newPin);
            $user->save();

            return [
                'status' => GeneralStatus::SUCCESS,
                'message' => 'PIN reset successful'
            ];

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
     * Create chat user account
     */
    protected function createChatUserAccount(array $data): ChatUser
    {
        $user = new ChatUser();
        $user->phone_number = $this->formatPhoneNumber($data['phone_number']);
        $user->account_number = $data['account_number'];
        $user->is_verified = true;
        if (isset($data['pin'])) {
            $user->pin = Hash::make($data['pin']);
        }
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
