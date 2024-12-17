<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\ChatUser;
use App\Models\Transaction;
use App\Services\AccountService;
use App\Common\GeneralStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Mockery;

class AccountServiceTest extends TestCase
{
    use RefreshDatabase, BaseServiceTestTrait;

    private AccountService $accountService;
    private ChatUser $user;
    private string $validPin = '1234';

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountService = new AccountService();
        $this->user = ChatUser::factory()->create([
            'account_number' => '1234567890',
            'pin' => Hash::make($this->validPin)
        ]);
    }

    public function test_get_balance_validates_pin()
    {
        $this->mockPinValidation($this->user->account_number, false);

        $result = $this->accountService->getBalance($this->user->account_number, 'wrong-pin');

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertStringContainsString('Error validating PIN', $result['message']);
    }

    public function test_get_balance_success()
    {
        $this->mockPinValidation($this->user->account_number, true);

        $result = $this->accountService->getBalance($this->user->account_number, $this->validPin);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Balance retrieved successfully', $result['message']);
        $this->assertArrayHasKey('available_balance', $result['data']);
        $this->assertArrayHasKey('current_balance', $result['data']);
        $this->assertArrayHasKey('hold_amount', $result['data']);
        $this->assertArrayHasKey('currency', $result['data']);
    }

    public function test_get_mini_statement_validates_pin()
    {
        $this->mockPinValidation($this->user->account_number, false);

        $result = $this->accountService->getMiniStatement($this->user->account_number, 'wrong-pin');

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertStringContainsString('Error validating PIN', $result['message']);
    }

    public function test_get_mini_statement_returns_last_five_transactions()
    {
        $this->mockPinValidation($this->user->account_number, true);

        // Create 7 transactions
        Transaction::factory()->count(7)->create([
            'sender' => $this->user->account_number
        ]);

        $result = $this->accountService->getMiniStatement($this->user->account_number, $this->validPin);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Mini statement retrieved', $result['message']);
        $this->assertCount(5, $result['data']['transactions']); // Should only return last 5
    }

    public function test_get_full_statement_validates_pin()
    {
        $this->mockPinValidation($this->user->account_number, false);

        $result = $this->accountService->getFullStatement($this->user->account_number, 'wrong-pin');

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertStringContainsString('Error validating PIN', $result['message']);
    }

    public function test_get_full_statement_applies_filters()
    {
        $this->mockPinValidation($this->user->account_number, true);

        // Create transactions of different types
        Transaction::factory()->internal()->count(3)->create([
            'sender' => $this->user->account_number,
            'amount' => 1000
        ]);
        Transaction::factory()->bankTransfer()->count(2)->create([
            'sender' => $this->user->account_number,
            'amount' => 2000
        ]);

        $filters = [
            'type' => 'internal',
            'min_amount' => 500,
            'max_amount' => 1500,
            'per_page' => 10
        ];

        $result = $this->accountService->getFullStatement(
            $this->user->account_number,
            $this->validPin,
            $filters
        );

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Full statement retrieved', $result['message']);
        $this->assertCount(3, $result['data']['transactions']); // Only internal transactions
        $this->assertArrayHasKey('pagination', $result['data']);
    }

    public function test_get_account_limits_for_nonexistent_account()
    {
        $result = $this->accountService->getAccountLimits('nonexistent-account');

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertStringContainsString('Account not found', $result['message']);
    }

    public function test_get_account_limits_success()
    {
        $user = ChatUser::factory()->create([
            'account_class' => 'premium'
        ]);

        $result = $this->accountService->getAccountLimits($user->account_number);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Account limits retrieved', $result['message']);
        $this->assertEquals('premium', $result['data']['account_class']);
        $this->assertArrayHasKey('limits', $result['data']);
        $this->assertArrayHasKey('usage', $result['data']);
    }

    public function test_get_account_profile_for_nonexistent_account()
    {
        $result = $this->accountService->getAccountProfile('nonexistent-account');

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertStringContainsString('Account not found', $result['message']);
    }

    public function test_get_account_profile_success()
    {
        $user = ChatUser::factory()->create([
            'phone_number' => '254712345678',
            'account_number' => '1234567890',
            'account_class' => 'standard',
            'is_verified' => true
        ]);

        $result = $this->accountService->getAccountProfile($user->account_number);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Account profile retrieved', $result['message']);
        $this->assertEquals($user->phone_number, $result['data']['phone_number']);
        $this->assertEquals($user->account_number, $result['data']['account_number']);
        $this->assertEquals('standard', $result['data']['account_class']);
    }

    public function test_transaction_description_formatting()
    {
        $this->mockPinValidation($this->user->account_number, true);

        // Test different transaction types
        Transaction::factory()->internal()->create([
            'sender' => $this->user->account_number
        ]);
        Transaction::factory()->bankTransfer()->create([
            'sender' => $this->user->account_number
        ]);
        Transaction::factory()->billPayment()->create([
            'sender' => $this->user->account_number
        ]);

        $result = $this->accountService->getMiniStatement($this->user->account_number, $this->validPin);

        $transactions = collect($result['data']['transactions']);

        // Verify descriptions are properly formatted
        $this->assertTrue($transactions->some(fn($t) => str_contains($t['description'], 'Transfer to')));
        $this->assertTrue($transactions->some(fn($t) => str_contains($t['description'], 'Bank transfer to')));
        $this->assertTrue($transactions->some(fn($t) => str_contains($t['description'], 'Bill payment -')));
    }

    public function test_daily_and_monthly_usage_calculation()
    {
        // Create transactions from different periods
        Transaction::factory()->successful()->count(2)->create([
            'sender' => $this->user->account_number,
            'amount' => 1000,
            'created_at' => now()
        ]);
        Transaction::factory()->successful()->create([
            'sender' => $this->user->account_number,
            'amount' => 1000,
            'created_at' => now()->subDays(2)
        ]);

        $result = $this->accountService->getAccountLimits($this->user->account_number);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('KES 2,000.00', $result['data']['usage']['daily']['amount']); // Only today's transactions
        $this->assertEquals('KES 3,000.00', $result['data']['usage']['monthly']['amount']); // All transactions this month
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
