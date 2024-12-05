<?php

namespace App\Http\Controllers\Chat;

class BillPaymentController extends BaseMessageController
{
    public function handleBillPayment(array $message, array $sessionData): array
    {
        return $this->formatTextResponse("Please enter the bill account number:");
    }
}
