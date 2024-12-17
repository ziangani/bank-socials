<?php

namespace Database\Factories;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $types = ['internal', 'bank', 'mobile_money', 'bill_payment'];
        $type = $this->faker->randomElement($types);

        $metadata = match($type) {
            'bank' => [
                'bank_details' => [
                    'bank_name' => $this->faker->company(),
                    'branch_code' => $this->faker->numerify('###'),
                ]
            ],
            'bill_payment' => [
                'bill_type' => $this->faker->randomElement(['electricity', 'water', 'internet']),
                'bill_number' => $this->faker->numerify('########'),
            ],
            default => []
        };

        return [
            'reference' => strtoupper($this->faker->bothify('TX-????-####')),
            'sender' => $this->faker->numerify('##########'),
            'recipient' => $this->faker->numerify('##########'),
            'amount' => $this->faker->randomFloat(2, 10, 10000),
            'fee' => $this->faker->randomFloat(2, 0, 100),
            'type' => $type,
            'status' => $this->faker->randomElement(['pending', 'success', 'failed']),
            'metadata' => $metadata,
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'updated_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    public function successful(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'success',
            ];
        });
    }

    public function internal(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'internal',
                'metadata' => [],
            ];
        });
    }

    public function bankTransfer(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'bank',
                'metadata' => [
                    'bank_details' => [
                        'bank_name' => $this->faker->company(),
                        'branch_code' => $this->faker->numerify('###'),
                    ]
                ],
            ];
        });
    }

    public function billPayment(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'bill_payment',
                'metadata' => [
                    'bill_type' => $this->faker->randomElement(['electricity', 'water', 'internet']),
                    'bill_number' => $this->faker->numerify('########'),
                ],
            ];
        });
    }
}
