<?php

namespace App\Http\Controllers\Chat;

class RegistrationController extends BaseMessageController
{
    public function handleRegistration(array $message, array $sessionData): array
    {
        return $this->formatMenuResponse(
            "Please select registration type:\n\n",
            $this->getMenuConfig('registration')
        );
    }

    public function processCardRegistration(array $message, array $sessionData): array
    {
        return $this->formatTextResponse("Please enter your 16-digit card number:");
    }

    public function processAccountRegistration(array $message, array $sessionData): array
    {
        return $this->formatTextResponse("Please enter your account number:");
    }
}
