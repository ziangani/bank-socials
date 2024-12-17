<?php

namespace Tests\Feature\Traits;

use App\Models\ChatUser;
use App\Models\ChatUserLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Carbon\Carbon;

trait TestHelpers
{
    use SessionManagement;
    use UserManagement;
    use AssertionHelpers;
    use MockManagement;
}

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

trait UserManagement
{
    protected function createTestUser(array $overrides = []): ChatUser
    {
        return ChatUser::factory()->create(array_merge([
            'phone_number' => '254712345678',
            'account_number' => '1234567890',
            'pin' => Hash::make('1234'),
            'is_verified' => true
        ], $overrides));
    }

    protected function createAuthenticatedSession(ChatUser $user = null): void
    {
        $user = $user ?? $this->user;
        ChatUserLogin::createLogin($user, 'test-session');
        
        $this->setSessionState('WELCOME', [
            'authenticated_user' => $user
        ]);
    }
}

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
}

trait MockManagement
{
    protected function setupChannelAdapter(): void
    {
        $adapter = $this->mock($this->getAdapterClass(), function ($mock) {
            $mock->shouldReceive('parseIncomingMessage')
                ->andReturnUsing(fn($data) => [
                    'sender' => $data['sender'],
                    'session_id' => $data['session_id'],
                    'content' => $data['content'],
                    'message_id' => 'msg_' . time()
                ]);

            $mock->shouldReceive('getSessionData')
                ->andReturnUsing(fn($sessionId) => $this->sessionData[$sessionId] ?? null);

            $mock->shouldReceive('updateSession')
                ->andReturnUsing(function($sessionId, $data) {
                    $this->sessionData[$sessionId] = $data;
                    return true;
                });

            $mock->shouldReceive('markMessageAsRead')->byDefault();
            $mock->shouldReceive('isMessageProcessed')->andReturn(false);
            $mock->shouldReceive('markMessageAsProcessed')->byDefault();
            $mock->shouldReceive('sendMessage')->byDefault();
            $mock->shouldReceive('formatOutgoingMessage')->andReturnArg(0);
            $mock->shouldReceive('endSession')->andReturn(true);
            $mock->shouldReceive('formatButtons')->andReturn([]);

            // Channel-specific expectations
            $this->setupChannelSpecificExpectations($mock);
        });

        $this->app->instance($this->getAdapterClass(), $adapter);
    }

    /**
     * Override this in your test class to add channel-specific mock expectations
     */
    protected function setupChannelSpecificExpectations($mock): void
    {
        // Add channel-specific mock expectations here
    }

    /**
     * Override this in your test class to return the correct adapter class
     */
    protected function getAdapterClass(): string
    {
        throw new \RuntimeException('You must implement getAdapterClass() in your test class');
    }
}
