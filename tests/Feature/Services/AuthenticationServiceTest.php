<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\ChatUser;
use App\Services\AuthenticationService;
use App\Common\GeneralStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Mockery;

class AuthenticationServiceTest extends TestCase
{
    use RefreshDatabase, BaseServiceTestTrait;

    private AuthenticationService $authService;
    private const TEST_OTP = '123456';

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthenticationService();
    }

    public function test_register_with_account_validates_account_number()
    {
        $data = [
            'account_number' => '123', // Invalid account number
            'phone_number' => '254712345678'
        ];

        $result = $this->authService->registerWithAccount($data);

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Invalid account number', $result['message']);
    }

    public function test_register_with_account_validates_phone_number()
    {
        $data = [
            'account_number' => '1234567890',
            'phone_number' => '' // Empty phone number
        ];

        $result = $this->authService->registerWithAccount($data);

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Phone number is required', $result['message']);
    }

    public function test_register_with_account_success()
    {
        $data = [
            'account_number' => '1234567890',
            'phone_number' => '254712345678'
        ];

        $this->mockOTPGeneration($data['phone_number'], self::TEST_OTP);

        $result = $this->authService->registerWithAccount($data);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('OTP sent successfully', $result['message']);
        $this->assertTrue($result['data']['requires_otp']);
        $this->assertNotEmpty($result['data']['reference']);
        if (config('app.debug')) {
            $this->assertEquals(self::TEST_OTP, $result['data']['otp']);
        }
    }

    public function test_verify_registration_otp_with_invalid_otp()
    {
        $data = [
            'account_number' => '1234567890',
            'phone_number' => '254712345678'
        ];

        $this->mockOTPValidation($data['phone_number'], self::TEST_OTP, false);

        $result = $this->authService->verifyRegistrationOTP('REF123', 'wrong-otp', $data);

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Invalid OTP', $result['message']);
    }

    public function test_verify_registration_otp_success()
    {
        $data = [
            'account_number' => '1234567890',
            'phone_number' => '254712345678'
        ];

        $this->mockOTPValidation($data['phone_number'], self::TEST_OTP, true);

        $result = $this->authService->verifyRegistrationOTP('REF123', self::TEST_OTP, $data);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('Registration successful', $result['message']);
        $this->assertNotEmpty($result['data']['user_id']);

        // Verify user was created
        $this->assertDatabaseHas('chat_users', [
            'phone_number' => $data['phone_number'],
            'account_number' => $data['account_number'],
            'is_verified' => true
        ]);
    }

    public function test_setup_pin_validates_pin_format()
    {
        $user = ChatUser::factory()->create();
        
        $result = $this->authService->setupPIN($user->id, '123'); // Invalid PIN

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Invalid PIN format. PIN must be 4 digits.', $result['message']);
    }

    public function test_setup_pin_success()
    {
        $user = ChatUser::factory()->create();
        
        $result = $this->authService->setupPIN($user->id, '1234');

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('PIN setup successful', $result['message']);
        
        // Verify PIN was hashed and saved
        $user->refresh();
        $this->assertTrue(Hash::check('1234', $user->pin));
    }

    public function test_change_pin_validates_old_pin()
    {
        $user = ChatUser::factory()->create([
            'pin' => Hash::make('1234')
        ]);
        
        $result = $this->authService->changePIN($user->id, '5678', '4321');

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Invalid current PIN', $result['message']);
    }

    public function test_change_pin_validates_new_pin_format()
    {
        $user = ChatUser::factory()->create([
            'pin' => Hash::make('1234')
        ]);
        
        $result = $this->authService->changePIN($user->id, '1234', '123'); // Invalid new PIN

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Invalid PIN format. PIN must be 4 digits.', $result['message']);
    }

    public function test_change_pin_success()
    {
        $user = ChatUser::factory()->create([
            'pin' => Hash::make('1234')
        ]);
        
        $result = $this->authService->changePIN($user->id, '1234', '5678');

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('PIN changed successfully', $result['message']);
        
        // Verify PIN was updated
        $user->refresh();
        $this->assertTrue(Hash::check('5678', $user->pin));
    }

    public function test_reset_pin_sends_otp()
    {
        $user = ChatUser::factory()->create([
            'phone_number' => '254712345678'
        ]);

        $this->mockOTPGeneration($user->phone_number, self::TEST_OTP);
        
        $result = $this->authService->resetPIN($user->id);

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('OTP sent successfully', $result['message']);
        $this->assertTrue($result['data']['requires_otp']);
        $this->assertNotEmpty($result['data']['reference']);
        if (config('app.debug')) {
            $this->assertEquals(self::TEST_OTP, $result['data']['otp']);
        }
    }

    public function test_verify_pin_reset_otp_with_invalid_otp()
    {
        $user = ChatUser::factory()->create([
            'phone_number' => '254712345678'
        ]);

        $this->mockOTPValidation($user->phone_number, self::TEST_OTP, false);
        
        $result = $this->authService->verifyPINResetOTP('REF123', 'wrong-otp', $user->id, '1234');

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Invalid OTP', $result['message']);
    }

    public function test_verify_pin_reset_otp_validates_pin_format()
    {
        $user = ChatUser::factory()->create([
            'phone_number' => '254712345678'
        ]);

        $this->mockOTPValidation($user->phone_number, self::TEST_OTP, true);
        
        $result = $this->authService->verifyPINResetOTP('REF123', self::TEST_OTP, $user->id, '123'); // Invalid PIN

        $this->assertEquals(GeneralStatus::ERROR, $result['status']);
        $this->assertEquals('Invalid PIN format. PIN must be 4 digits.', $result['message']);
    }

    public function test_verify_pin_reset_otp_success()
    {
        $user = ChatUser::factory()->create([
            'phone_number' => '254712345678'
        ]);

        $this->mockOTPValidation($user->phone_number, self::TEST_OTP, true);
        
        $result = $this->authService->verifyPINResetOTP('REF123', self::TEST_OTP, $user->id, '1234');

        $this->assertEquals(GeneralStatus::SUCCESS, $result['status']);
        $this->assertEquals('PIN reset successful', $result['message']);

        // Verify PIN was updated
        $user->refresh();
        $this->assertTrue(Hash::check('1234', $user->pin));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
