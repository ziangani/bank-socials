<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => '254' . $this->faker->numerify('#########'),
            'account_number' => $this->faker->unique()->numerify('##########'),
            'account_class' => $this->faker->randomElement(['standard', 'premium', 'platinum']),
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function unverified(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'email_verified_at' => null,
            ];
        });
    }

    public function standard(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'account_class' => 'standard',
            ];
        });
    }

    public function premium(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'account_class' => 'premium',
            ];
        });
    }

    public function platinum(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'account_class' => 'platinum',
            ];
        });
    }
}
