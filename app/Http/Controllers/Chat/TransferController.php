<?php

namespace App\Http\Controllers\Chat;

class TransferController extends BaseMessageController
{
    public function handleTransfer(array $message, array $sessionData): array
    {
        return $this->formatMenuResponse(
            "Please select transfer type:\n\n",
            $this->getMenuConfig('transfer')
        );
    }

    public function processInternalTransfer(array $message, array $sessionData): array
    {
        return $this->formatTextResponse("Please enter recipient's account number:");
    }

    public function processBankTransfer(array $message, array $sessionData): array
    {
        return $this->formatTextResponse("Please enter recipient's bank account number:");
    }

    public function processMobileMoneyTransfer(array $message, array $sessionData): array
    {
        return $this->formatTextResponse("Please enter recipient's mobile number:");
    }
}
