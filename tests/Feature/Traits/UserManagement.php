<?php

namespace Tests\Feature\Traits;

use App\Models\ChatUser;
use App\Models\ChatUserLogin;
use Illuminate\Support\Facades\Hash;

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

    protected function createTestTransaction(array $attributes = []): void
    {
        $this->user->transactions()->create(array_merge([
            'type' => 'TRANSFER',
            'amount' => 1000,
            'status' => 'SUCCESS'
        ], $attributes));
    }
}
