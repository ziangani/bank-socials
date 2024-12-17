<?php

namespace Tests\Feature\Traits;

use Illuminate\Http\Request;

trait SessionManagement
{
    protected function setSessionState(string $state, array $data = []): void
    {
        $this->sessionData['test-session'] = [
            'state' => $state,
            'data' => $data
        ];
    }

    protected function getSessionState(): ?array
    {
        return $this->sessionData['test-session'] ?? null;
    }

    protected function processMessage(string $content): array
    {
        $request = new Request([
            'sender' => $this->user->phone_number,
            'session_id' => 'test-session',
            'content' => $content
        ]);

        $response = $this->controller->processMessage($request);
        return json_decode($response->getContent(), true);
    }

    protected function setAuthenticationState(): void
    {
        $this->setSessionState('AUTHENTICATION');
    }

    protected function setOtpVerificationState(string $otp): void
    {
        $this->setSessionState('OTP_VERIFICATION', [
            'otp' => $otp,
            'otp_generated_at' => now(),
            'is_authentication' => true
        ]);
    }
}
