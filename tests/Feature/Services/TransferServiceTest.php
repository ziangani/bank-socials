<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Transaction;
use App\Models\ChatUser;
use App\Services\TransferService;
use App\Common\GeneralStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;

class TransferServiceTest extends TestCase
{
    use RefreshDatabase, BaseServiceTestTrait;

    private TransferService $transferService;
    private array $validInternalTransferData;
    private array $validBankTransferData;
    private array $validMobileMoneyData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transferService = new TransferService();

        $this->validInternalTransferData = [
            'sender' => '1234567890',
            'recipient' => '0987654321',
            'amount' => 1000.00,
            'pin' => '1234'
        ];

        $this->validBankTransferData = [
            'sender' => '1234567890',
            'recipient' => '0987654321',
            'amount' => 1000.00,
            'pin' => '1234',
            'bank_name' => 'Test Bank',
            'bank_account' => '1234567890'
        ];

        $this->validMobileMoneyData = [
            'sender' => '1234567890',
            'recipient' => '254712345678',
            'amount' => 1000.00,
            'pin' => '1234'
        ];

        // Set up default Cache mock behavior
        Cache::shouldReceive('get')
            ->with('pin_attempts_' . $this->validInternalTransferData['sender'])
            ->andReturn(0)
            ->byDefault();

        Cache::shouldReceive('forget')
            ->with('pin_attempts_' . $this->validInternalTransferData['sender'])
            ->byDefault();
    }

    public function test_internal_transfer_validates_amount()
    {
        $data = $this->validInternalTransferData;
        $data['amount'] = 0; // Invalid amount

        $result = $this->transferService->internalTransfer($data);

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertStringContainsString('Amount must be at least', $result['message']);
    }

    public function test_internal_transfer_validates_pin()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('pin_attempts_' . $this->validInternalTransferData['sender'])
            ->andReturn(0);

        Cache::shouldReceive('put')
            ->once()
            ->with('pin_attempts_' . $this->validInternalTransferData['sender'], 1, \Mockery::any());

        $data = $this->validInternalTransferData;
        $data['pin'] = 'wrong-pin';

        $result = $this->transferService->internalTransfer($data);

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertStringContainsString('Error validating PIN', $result['message']);
    }

    public function test_internal_transfer_success()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('pin_attempts_' . $this->validInternalTransferData['sender'])
            ->andReturn(0);

        Cache::shouldReceive('forget')
            ->once()
            ->with('pin_attempts_' . $this->validInternalTransferData['sender']);

        $result = $this->transferService->internalTransfer($this->validInternalTransferData);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Transfer successful', $result['message']);
        $this->assertArrayHasKey('reference', $result['data']);
        $this->assertEquals(
            $this->formatAmount($this->validInternalTransferData['amount']),
            $result['data']['amount']
        );

        // Verify transaction was created
        $this->assertDatabaseHas('transactions', [
            'type' => 'internal',
            'sender' => $this->validInternalTransferData['sender'],
            'recipient' => $this->validInternalTransferData['recipient'],
            'amount' => $this->validInternalTransferData['amount'],
            'status' => 'pending'
        ]);
    }

    public function test_bank_transfer_validates_bank_details()
    {
        $data = $this->validBankTransferData;
        unset($data['bank_name']); // Missing bank name

        $result = $this->transferService->bankTransfer($data);

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Bank details are required', $result['message']);
    }

    public function test_bank_transfer_calculates_fees()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('pin_attempts_' . $this->validBankTransferData['sender'])
            ->andReturn(0);

        Cache::shouldReceive('forget')
            ->once()
            ->with('pin_attempts_' . $this->validBankTransferData['sender']);

        config(['social-banking.fees.external.fixed' => 50]);
        config(['social-banking.fees.external.percentage' => 1]);

        $result = $this->transferService->bankTransfer($this->validBankTransferData);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        
        // Verify fees calculation (50 fixed + 1% of 1000)
        $expectedFees = 50 + ($this->validBankTransferData['amount'] * 0.01);
        $this->assertEquals(
            $this->formatAmount($expectedFees),
            $result['data']['fees']
        );
    }

    public function test_mobile_money_transfer_validates_phone_number()
    {
        $data = $this->validMobileMoneyData;
        $data['recipient'] = '123'; // Invalid phone number

        $result = $this->transferService->mobileMoneyTransfer($data);

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Invalid recipient phone number', $result['message']);
    }

    public function test_mobile_money_transfer_calculates_fees()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with('pin_attempts_' . $this->validMobileMoneyData['sender'])
            ->andReturn(0);

        Cache::shouldReceive('forget')
            ->once()
            ->with('pin_attempts_' . $this->validMobileMoneyData['sender']);

        config(['social-banking.fees.mobile_money.fixed' => 30]);
        config(['social-banking.fees.mobile_money.percentage' => 0.5]);

        $result = $this->transferService->mobileMoneyTransfer($this->validMobileMoneyData);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        
        // Verify fees calculation (30 fixed + 0.5% of 1000)
        $expectedFees = 30 + ($this->validMobileMoneyData['amount'] * 0.005);
        $this->assertEquals(
            $this->formatAmount($expectedFees),
            $result['data']['fees']
        );
    }

    public function test_check_transfer_status_for_nonexistent_reference()
    {
        $result = $this->transferService->checkTransferStatus('NONEXISTENT');

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Transaction not found', $result['message']);
    }

    public function test_check_transfer_status_success()
    {
        $transaction = Transaction::factory()->create([
            'reference' => 'TRF123',
            'status' => 'success',
            'type' => 'internal'
        ]);

        $result = $this->transferService->checkTransferStatus($transaction->reference);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Transfer status retrieved', $result['message']);
        $this->assertEquals('success', $result['data']['status']);
        $this->assertEquals('internal', $result['data']['type']);
    }

    public function test_transfer_handles_database_transaction()
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->never();
        DB::shouldReceive('rollBack')->once();

        Cache::shouldReceive('get')
            ->once()
            ->with('pin_attempts_' . $this->validInternalTransferData['sender'])
            ->andReturn(0);

        Cache::shouldReceive('forget')
            ->once()
            ->with('pin_attempts_' . $this->validInternalTransferData['sender']);

        // Create a mock transaction that throws an error
        $transaction = Mockery::mock(Transaction::class);
        $transaction->shouldReceive('save')->andThrow(new \Exception('Database error'));
        
        // Bind the mock to the container
        $this->app->instance(Transaction::class, $transaction);

        $result = $this->transferService->internalTransfer($this->validInternalTransferData);

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertStringContainsString('Internal transfer failed', $result['message']);
    }

    private function formatAmount(float $amount): string
    {
        return 'KES ' . number_format($amount, 2);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
