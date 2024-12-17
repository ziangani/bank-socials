<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Transaction;
use App\Services\BillPaymentService;
use App\Common\GeneralStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;

class BillPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private BillPaymentService $billService;
    private array $validBillData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billService = new BillPaymentService();
        
        $this->validBillData = [
            'payer' => '1234567890',
            'bill_account' => 'BILL123',
            'bill_type' => 'water',
            'amount' => 150.00,
            'bill_reference' => 'BILL001'
        ];
    }

    public function test_validate_bill_account_success()
    {
        $result = $this->billService->validateBillAccount('BILL123', 'water');

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Bill account validated', $result['message']);
        $this->assertArrayHasKey('account_name', $result['data']);
        $this->assertArrayHasKey('bills', $result['data']);
        $this->assertNotEmpty($result['data']['bills']);
    }

    public function test_process_bill_payment_validates_amount()
    {
        $data = $this->validBillData;
        $data['amount'] = 0; // Invalid amount

        $result = $this->billService->processBillPayment($data);

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertStringContainsString('Amount must be at least', $result['message']);
    }

    public function test_process_bill_payment_calculates_fees()
    {
        config(['social-banking.fees.bill.fixed' => 30]);
        config(['social-banking.fees.bill.percentage' => 1.5]);

        $result = $this->billService->processBillPayment($this->validBillData);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Bill payment successful', $result['message']);
        
        // Verify fees calculation
        $expectedFees = 30 + ($this->validBillData['amount'] * 0.015);
        $this->assertEquals(
            $this->formatAmount($expectedFees),
            $result['data']['fees']
        );
    }

    public function test_process_bill_payment_creates_transaction()
    {
        $result = $this->billService->processBillPayment($this->validBillData);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        
        // Verify transaction was created
        $this->assertDatabaseHas('transactions', [
            'type' => 'bill_payment',
            'sender' => $this->validBillData['payer'],
            'recipient' => $this->validBillData['bill_account'],
            'amount' => $this->validBillData['amount'],
            'status' => 'pending'
        ]);
    }

    public function test_check_payment_status_for_nonexistent_reference()
    {
        $result = $this->billService->checkPaymentStatus('NONEXISTENT');

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Bill payment not found', $result['message']);
    }

    public function test_check_payment_status_success()
    {
        $transaction = Transaction::factory()->billPayment()->create([
            'reference' => 'BIL123',
            'status' => 'success',
            'metadata' => [
                'bill_type' => 'water',
                'bill_account' => 'BILL123'
            ]
        ]);

        $result = $this->billService->checkPaymentStatus($transaction->reference);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Payment status retrieved', $result['message']);
        $this->assertEquals('success', $result['data']['status']);
        $this->assertEquals('water', $result['data']['bill_type']);
        $this->assertEquals('BILL123', $result['data']['bill_account']);
    }

    public function test_get_payment_history_without_bill_type()
    {
        // Create various transactions
        Transaction::factory()->billPayment()->count(3)->create([
            'sender' => '1234567890',
            'metadata' => [
                'bill_type' => 'water',
                'bill_account' => 'BILL123'
            ]
        ]);
        Transaction::factory()->billPayment()->count(2)->create([
            'sender' => '1234567890',
            'metadata' => [
                'bill_type' => 'electricity',
                'bill_account' => 'ELEC123'
            ]
        ]);

        $result = $this->billService->getPaymentHistory('1234567890');

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Payment history retrieved', $result['message']);
        $this->assertCount(5, $result['data']['history']); // All bill types
    }

    public function test_get_payment_history_with_bill_type_filter()
    {
        // Create various transactions
        Transaction::factory()->billPayment()->count(3)->create([
            'sender' => '1234567890',
            'metadata' => [
                'bill_type' => 'water',
                'bill_account' => 'BILL123'
            ]
        ]);
        Transaction::factory()->billPayment()->count(2)->create([
            'sender' => '1234567890',
            'metadata' => [
                'bill_type' => 'electricity',
                'bill_account' => 'ELEC123'
            ]
        ]);

        $result = $this->billService->getPaymentHistory('1234567890', 'water');

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Payment history retrieved', $result['message']);
        $this->assertCount(3, $result['data']['history']); // Only water bills
        foreach ($result['data']['history'] as $payment) {
            $this->assertEquals('water', $payment['bill_type']);
        }
    }

    public function test_get_payment_history_by_bill_account()
    {
        // Create transactions for different bill accounts
        Transaction::factory()->billPayment()->count(2)->create([
            'sender' => '1234567890',
            'metadata' => [
                'bill_type' => 'water',
                'bill_account' => 'BILL123'
            ]
        ]);
        Transaction::factory()->billPayment()->count(3)->create([
            'sender' => '0987654321',
            'metadata' => [
                'bill_type' => 'water',
                'bill_account' => 'BILL123' // Same bill account, different payer
            ]
        ]);

        $result = $this->billService->getPaymentHistory('BILL123');

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertCount(5, $result['data']['history']); // All payments for the bill account
    }

    public function test_process_bill_payment_handles_database_transaction()
    {
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('commit')->never();
        DB::shouldReceive('rollBack')->once();

        // Create a mock transaction that throws an error
        $transaction = Mockery::mock(Transaction::class);
        $transaction->shouldReceive('save')->andThrow(new \Exception('Database error'));
        
        // Bind the mock to the container
        $this->app->instance(Transaction::class, $transaction);

        $result = $this->billService->processBillPayment($this->validBillData);

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertStringContainsString('Bill payment failed', $result['message']);
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
