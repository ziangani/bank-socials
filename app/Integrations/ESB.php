<?php

namespace App\Integrations;

class ESB
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('ESB_API_KEY');
        $this->baseUrl = env('ESB_BASE_URL');

        if (empty($this->apiKey)) {
            throw new \RuntimeException('ESB API key not configured');
        }
        if (empty($this->baseUrl)) {
            throw new \RuntimeException('ESB base URL not configured');
        }
    }

    private const ENDPOINTS = [
        'account_details' => '/third-party/mobile-banking/cbs_account_details',
        'transfer' => '/third-party/mobile-banking/bank_to_bank_transfer',
        'otp' => '/general/otp/generate'
    ];

    private function sendRequest(string $endpoint, array $data): array
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->baseUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        return [
            'response' => json_decode($response, true),
            'httpCode' => $httpCode
        ];
    }

    public function getAccountDetailsAndBalance(string $accountNumber): array
    {
        $result = $this->sendRequest(self::ENDPOINTS['account_details'], [
            'account_number' => $accountNumber,
            'api_key' => $this->apiKey
        ]);

        $decodedResponse = $result['response'];
        $httpCode = $result['httpCode'];

        if ($httpCode === 404 || !$decodedResponse['status']) {
            $errorMessage = 'Account details not found or service unavailable';
            if (isset($decodedResponse['message'])) {
                $errorMessage = is_array($decodedResponse['message']) ? "Account not found" : $decodedResponse['message'];
            }
            return [
                'status' => false,
                'data' => [],
                'message' => $errorMessage
            ];
        }

        return [
            'status' => true,
            'data' => $decodedResponse['data']['bank_profile'] ?? [],
            'message' => $decodedResponse['message'] ?? 'success'
        ];
    }

    public function generateOTP(string $mobileNumber): array
    {
        $result = $this->sendRequest(self::ENDPOINTS['otp'], [
            'mobile_number' => $mobileNumber
        ]);

        $decodedResponse = $result['response'];
        $httpCode = $result['httpCode'];

        if ($httpCode !== 201 || !$decodedResponse['status']) {
            $errorMessage = 'OTP generation failed';
            if (isset($decodedResponse['message'])) {
                $errorMessage = is_array($decodedResponse['message'])
                    ? json_encode($decodedResponse['message'])
                    : $decodedResponse['message'];
            }
            return [
                'status' => false,
                'data' => [],
                'message' => $errorMessage
            ];
        }

        return [
            'status' => true,
            'data' => $decodedResponse['data'] ?? [],
            'message' => $decodedResponse['message'] ?? 'OTP generated successfully'
        ];
    }

    public function transferToBankAccount(string $fromAccount, string $toAccount, string $amount, string $description = 'Transfer'): array
    {
        $result = $this->sendRequest(self::ENDPOINTS['transfer'], [
            'from_account' => $fromAccount,
            'to_account' => $toAccount,
            'amount' => $amount,
            'description' => $description,
            'api_key' => $this->apiKey
        ]);

        $decodedResponse = $result['response'];
        $httpCode = $result['httpCode'];

        if ($httpCode !== 201 || !$decodedResponse['status']) {
            $errorMessage = 'Transfer failed';
            if (!empty($decodedResponse['errors']['error'])) {
                $errorMessage = $decodedResponse['errors']['error'];
            } elseif (isset($decodedResponse['message'])) {
                $errorMessage = is_array($decodedResponse['message'])
                    ? json_encode($decodedResponse['message'])
                    : $decodedResponse['message'];
            }
            return [
                'status' => false,
                'data' => [],
                'message' => $errorMessage
            ];
        }

        return [
            'status' => true,
            'data' => $decodedResponse['data'] ?? [],
            'message' => $decodedResponse['message'] ?? 'Transfer successful'
        ];
    }
}
