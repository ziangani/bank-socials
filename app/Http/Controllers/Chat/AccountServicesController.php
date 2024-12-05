<?php

namespace App\Http\Controllers\Chat;

class AccountServicesController extends BaseMessageController
{
    public function handleAccountServices(array $message, array $sessionData): array
    {
        return $this->formatMenuResponse(
            "Please select a service:\n\n",
            $this->getMenuConfig('account_services')
        );
    }

    public function processBalanceInquiry(array $message, array $sessionData): array
    {
        return $this->formatTextResponse("Please enter your PIN to view balance:");
    }

    public function processMiniStatement(array $message, array $sessionData): array
    {
        return $this->formatTextResponse("Please enter your PIN to view mini statement:");
    }

    public function processFullStatement(array $message, array $sessionData): array
    {
        return $this->formatTextResponse("Please enter your PIN to view full statement:");
    }

    public function processPINManagement(array $message, array $sessionData): array
    {
        return [
            'message' => "PIN Management:\n1. Change PIN\n2. Reset PIN\n3. Set Transaction PIN",
            'type' => 'interactive',
            'buttons' => [
                '1' => 'Change PIN',
                '2' => 'Reset PIN',
                '3' => 'Set Transaction PIN'
            ],
            'end_session' => false
        ];
    }
}
