<?php

namespace Tests\Feature\Traits;

trait AssertionHelpers
{
    protected function assertStateIs(string $expectedState): void
    {
        $this->assertEquals(
            $expectedState,
            $this->getSessionState()['state']
        );
    }

    protected function assertStateDataHas(array $expectedData): void
    {
        $stateData = $this->getSessionState()['data'];
        foreach ($expectedData as $key => $value) {
            $this->assertEquals($value, $stateData[$key]);
        }
    }

    protected function assertResponseHasMessage(string $message, array $response): void
    {
        $this->assertStringContains($message, $response['message']);
    }

    protected function assertMainMenuShown(array $response): void
    {
        $this->assertResponseHasMessage('Welcome to main menu', $response);
    }

    protected function assertErrorMessage(string $expectedError, array $response): void
    {
        $this->assertResponseHasMessage($expectedError, $response);
    }

    protected function assertLoginCreated(array $overrides = []): void
    {
        $this->assertDatabaseHas('chat_user_logins', array_merge([
            'chat_user_id' => $this->user->id,
            'session_id' => 'test-session',
            'phone_number' => $this->user->phone_number,
            'is_active' => true
        ], $overrides));
    }

    protected function assertTransactionCreated(array $attributes): void
    {
        $this->assertDatabaseHas('transactions', array_merge([
            'chat_user_id' => $this->user->id
        ], $attributes));
    }

    protected function assertPromptForPin(array $response): void
    {
        $this->assertResponseHasMessage('Please enter your PIN', $response);
    }

    protected function assertOtpSent(array $response): void
    {
        $this->assertResponseHasMessage('Please enter the 6-digit OTP', $response);
    }

    protected function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }

    protected function assertBalanceShown(array $response): void
    {
        $this->assertResponseHasMessage('Your current balance', $response);
    }

    protected function assertTransactionSuccess(array $response): void
    {
        $this->assertResponseHasMessage('Transaction successful', $response);
    }

    protected function assertValidationError(string $field, array $response): void
    {
        $this->assertResponseHasMessage("Invalid $field", $response);
    }

    protected function assertAuthenticationRequired(array $response): void
    {
        $this->assertResponseHasMessage('Please authenticate', $response);
    }

    protected function assertSessionExpired(array $response): void
    {
        $this->assertResponseHasMessage('Session expired', $response);
    }

    protected function assertTransactionFailed(string $reason, array $response): void
    {
        $this->assertResponseHasMessage("Transaction failed: $reason", $response);
    }

    protected function assertInsufficientBalance(array $response): void
    {
        $this->assertResponseHasMessage('Insufficient balance', $response);
    }

    protected function assertAmountPrompt(array $response): void
    {
        $this->assertResponseHasMessage('Enter amount', $response);
    }

    protected function assertRecipientPrompt(array $response): void
    {
        $this->assertResponseHasMessage('Enter recipient', $response);
    }

    protected function assertConfirmationPrompt(array $response): void
    {
        $this->assertResponseHasMessage('Confirm transaction', $response);
    }

    protected function assertBalanceUpdated(float $expectedBalance): void
    {
        $this->user->refresh();
        $this->assertEquals($expectedBalance, $this->user->balance);
    }

    protected function assertTransactionCount(int $expectedCount): void
    {
        $this->assertEquals(
            $expectedCount,
            $this->user->transactions()->count()
        );
    }
}
