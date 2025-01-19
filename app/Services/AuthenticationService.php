<?php

namespace App\Services;

use App\Common\GeneralStatus;
use App\Models\ChatUser;
use App\Models\ChatUserLogin;
use App\Integrations\ESB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthenticationService extends BaseService
{
    protected ESB $esb;

    public function __construct(ESB $esb)
    {
        $this->esb = $esb;
    }

    /**
     * Verify OTP and complete registration
     */
    public function verifyRegistrationOTP(string $reference, string $otp, array $data): array
    {
        try {
            // Compare OTP entered with what was generated
            if ($otp !== $data['otp']) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Invalid OTP'
                ];
            }

            // Check if OTP has expired
            if (isset($data['expires_at']) && now()->isAfter($data['expires_at'])) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'OTP has expired'
                ];
            }

            // Create chat user account only after successful OTP validation
            $user = new ChatUser();
            $user->phone_number = $this->formatPhoneNumber($data['phone_number']);
            $user->account_number = $data['account_number'];
            $user->is_verified = true;
            $user->last_otp_sent_at = Carbon::now();
            if (isset($data['pin'])) {
                $user->pin = Hash::make($data['pin']);
            }
            $user->save();

            // Create login record for the new user
            ChatUserLogin::createLogin($user, $reference);

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

            // Generate and send OTP through ESB
            $otpResult = $this->esb->generateOTP($user->phone_number);
            if (!$otpResult['status']) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => $otpResult['message'] ?? 'Failed to send OTP'
                ];
            }

            // Generate reference
            $reference = $this->generateReference('PIN');
            return [
                'status' => GeneralStatus::SUCCESS,
                'message' => 'OTP sent successfully',
                'data' => [
                    'requires_otp' => true,
                    'reference' => $reference,
                    'otp' => $otpResult['data']['otp'], // ESB OTP
                    'expires_at' => $otpResult['data']['expires_at'] // ESB expiry time
                ]
            ];

        } catch (\Exception $e) {
            return $this->logAndReturnError('PIN reset initiation failed', $e);
        }
    }

    /**
     * Verify PIN reset OTP and set new PIN
     */
    public function verifyPINResetOTP(string $reference, string $otp, string $userId, string $newPin, array $otpData): array
    {
        try {
            $user = ChatUser::find($userId);

            // Compare OTP entered with what was generated
            if ($otp !== $otpData['otp']) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'Invalid OTP'
                ];
            }

            // Check if OTP has expired
            if (isset($otpData['expires_at']) && now()->isAfter($otpData['expires_at'])) {
                return [
                    'status' => GeneralStatus::ERROR,
                    'message' => 'OTP has expired'
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
     * Validate account details using ESB
     */
    public function validateAccountDetails(array $data): array
    {
        // Validate phone number
        if (empty($data['phone_number'])) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => 'Phone number is required'
            ];
        }

        // Validate account through ESB
        $validation = $this->esb->getAccountDetailsAndBalance($data['account_number']);
        if (!$validation['status']) {
            return [
                'status' => GeneralStatus::ERROR,
                'message' => $validation['message'] ?? 'Invalid account number'
            ];
        }

        return [
            'status' => GeneralStatus::SUCCESS,
            'message' => 'Account details validated successfully',
            'data' => $validation['data'] ?? []
        ];
    }

    /**
     * Validate PIN format
     */
    protected function validatePINFormat(string $pin): bool
    {
        return preg_match('/^[0-9]{4}$/', $pin);
    }
}
