<?php

namespace Database\Factories;

use App\Models\ChatUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatUserFactory extends Factory
{
    protected $model = ChatUser::class;

    public function definition(): array
    {
        return [
            'phone_number' => '254' . $this->faker->numerify('#########'),
            'account_number' => $this->faker->numerify('##########'),
            'is_verified' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
