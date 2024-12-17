<?php

namespace Database\Factories;

use App\Models\ChatUser;
use App\Models\ChatUserLogin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class ChatUserLoginFactory extends Factory
{
    protected $model = ChatUserLogin::class;

    public function definition()
    {
        $user = ChatUser::factory()->create();
        return [
            'chat_user_id' => $user->id,
            'session_id' => $this->faker->uuid,
            'phone_number' => $user->phone_number,
            'authenticated_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes(30),
            'is_active' => true
        ];
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false
            ];
        });
    }

    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'expires_at' => Carbon::now()->subMinutes(5)
            ];
        });
    }

    public function forUser(ChatUser $user)
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'chat_user_id' => $user->id,
                'phone_number' => $user->phone_number
            ];
        });
    }
}
