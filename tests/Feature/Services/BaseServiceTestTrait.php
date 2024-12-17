<?php

namespace Tests\Feature\Services;

use Illuminate\Support\Facades\Cache;

trait BaseServiceTestTrait
{
    protected function mockPinValidation(string $accountNumber, bool $isValid = true): void
    {
        if ($isValid) {
            Cache::shouldReceive('get')
                ->once()
                ->with('pin_attempts_' . $accountNumber)
                ->andReturn(0);

            Cache::shouldReceive('forget')
                ->once()
                ->with('pin_attempts_' . $accountNumber);
        } else {
            Cache::shouldReceive('get')
                ->once()
                ->with('pin_attempts_' . $accountNumber)
                ->andReturn(0);

            Cache::shouldReceive('put')
                ->once()
                ->with('pin_attempts_' . $accountNumber, 1, \Mockery::any());
        }
    }

    protected function mockOTPGeneration(string $phoneNumber, string $otp = '123456'): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->withArgs(function ($key, $value, $ttl) use ($phoneNumber, $otp) {
                return $key === 'otp_' . $phoneNumber && $value === $otp;
            })
            ->andReturn(true);
    }

    protected function mockOTPValidation(string $phoneNumber, string $otp, bool $isValid = true): void
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('otp_' . $phoneNumber)
            ->andReturn($isValid ? $otp : null);

        if ($isValid) {
            Cache::shouldReceive('forget')
                ->once()
                ->with('otp_' . $phoneNumber);
        }
    }

    protected function formatAmount(float $amount): string
    {
        return 'KES ' . number_format($amount, 2);
    }
}
