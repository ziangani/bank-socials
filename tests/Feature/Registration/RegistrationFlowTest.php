<?php

namespace Tests\Feature\Registration;

use Tests\TestCase;
use App\Models\ChatUser;
use App\Models\ChatUserLogin;
use App\Services\AuthenticationService;
use App\Services\SessionManager;
use App\Adapters\WhatsAppMessageAdapter;
use App\Adapters\USSDMessageAdapter;
use App\Http\Controllers\Chat\RegistrationController;
use App\Common\GeneralStatus;
use App\Interfaces\MessageAdapterInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    private RegistrationController $ussdRegistrationController;
    private RegistrationController $whatsappRegistrationController;
    private $authService;
    private $sessionManager;
    private $ussdAdapter;
    private $whatsappAdapter;
    private string $validPhoneNumber = '254712345678';
    private string $validAccountNumber = '1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks using Mockery
        $this->authService = Mockery::mock(AuthenticationService::class);
        $this->sessionManager = Mockery::mock(SessionManager::class);
        
        // Create USSD adapter mock
        $this->ussdAdapter = Mockery::mock(USSDMessageAdapter::class);
        $this->ussdAdapter->shouldReceive('updateSession')->byDefault();
        $this->ussdRegistrationController = new RegistrationController(
            $this->ussdAdapter,
            $this->sessionManager,
            $this->authService
        );

        // Create WhatsApp adapter mock
        $this->whatsappAdapter = Mockery::mock(WhatsAppMessageAdapter::class);
        $this->whatsappAdapter->shouldReceive('updateSession')->byDefault();
        $this->whatsappRegistrationController = new RegistrationController(
            $this->whatsappAdapter,
            $this->sessionManager,
            $this->authService
        );
    }

    public function test_ussd_registration_flow()
    {
        // Step 1: Start Registration
        $message = [
            'sender' => $this->validPhoneNumber,
            'session_id' => 'test-session',
            'content' => ''
        ];
        $session = ['state' => 'START', 'data' => []];

        $this->authService->shouldReceive('validateAccountDetails')
            ->andReturn(['status' => GeneralStatus::SUCCESS]);

        // Test initial registration state
        $response = $this->ussdRegistrationController->handleRegistration($message, $session);
        $this->assertStringContains('Please enter your account number', $response['message']);

        // Step 2: Enter Account Number
        $message['content'] = $this->validAccountNumber;
        $session = [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => ['step' => RegistrationController::STATES['ACCOUNT_NUMBER_INPUT']]
        ];

        $response = $this->ussdRegistrationController->processAccountRegistration($message, $session);
        $this->assertStringContains('Please set up your PIN', $response['message']);

        // Step 3: Enter PIN
        $message['content'] = '1234';
        $session = [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => [
                'step' => RegistrationController::STATES['PIN_SETUP'],
                'account_number' => $this->validAccountNumber
            ]
        ];

        $response = $this->ussdRegistrationController->processAccountRegistration($message, $session);
        $this->assertStringContains('Please confirm your PIN', $response['message']);

        // Step 4: Confirm PIN
        $message['content'] = '1234';
        $session = [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => [
                'step' => RegistrationController::STATES['CONFIRM_PIN'],
                'account_number' => $this->validAccountNumber,
                'pin' => '1234'
            ]
        ];

        $this->authService->shouldReceive('registerWithAccount')
            ->andReturn([
                'status' => GeneralStatus::SUCCESS,
                'data' => [
                    'reference' => 'REF123',
                    'otp' => '123456',
                    'requires_otp' => true
                ]
            ]);

        $response = $this->ussdRegistrationController->processAccountRegistration($message, $session);
        $this->assertStringContains('verification code has been sent', $response['message']);

        // Step 5: Verify OTP
        $message['content'] = '123456';
        $session = [
            'state' => 'OTP_VERIFICATION',
            'data' => [
                'step' => RegistrationController::STATES['OTP_VERIFICATION'],
                'account_number' => $this->validAccountNumber,
                'pin' => '1234',
                'otp' => '123456',
                'otp_generated_at' => now(),
                'registration_reference' => 'REF123'
            ]
        ];

        $response = $this->ussdRegistrationController->processAccountRegistration($message, $session);

        // Verify user was created with correct data
        $user = ChatUser::where('phone_number', $this->validPhoneNumber)->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->validAccountNumber, $user->account_number);
        $this->assertTrue(Hash::check('1234', $user->pin));
        $this->assertTrue($user->is_verified);

        // Verify login record was created
        $this->assertDatabaseHas('chat_user_logins', [
            'chat_user_id' => $user->id,
            'session_id' => 'test-session'
        ]);
    }

    public function test_whatsapp_registration_flow()
    {
        // Step 1: Start Registration
        $message = [
            'sender' => $this->validPhoneNumber,
            'session_id' => 'test-session',
            'content' => ''
        ];
        $session = ['state' => 'START', 'data' => []];

        $this->authService->shouldReceive('validateAccountDetails')
            ->andReturn(['status' => GeneralStatus::SUCCESS]);

        // Test initial registration state
        $response = $this->whatsappRegistrationController->handleRegistration($message, $session);
        $this->assertStringContains('Please enter your account number', $response['message']);

        // Step 2: Enter Account Number
        $message['content'] = $this->validAccountNumber;
        $session = [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => ['step' => RegistrationController::STATES['ACCOUNT_NUMBER_INPUT']]
        ];

        $this->authService->shouldReceive('registerWithAccount')
            ->andReturn([
                'status' => GeneralStatus::SUCCESS,
                'data' => [
                    'reference' => 'REF123',
                    'otp' => '123456',
                    'requires_otp' => true
                ]
            ]);

        $response = $this->whatsappRegistrationController->processAccountRegistration($message, $session);
        $this->assertStringContains('Please enter the 6-digit OTP', $response['message']);

        // Step 3: Verify OTP
        $message['content'] = '123456';
        $session = [
            'state' => 'OTP_VERIFICATION',
            'data' => [
                'step' => RegistrationController::STATES['OTP_VERIFICATION'],
                'account_number' => $this->validAccountNumber,
                'otp' => '123456',
                'otp_generated_at' => now(),
                'registration_reference' => 'REF123'
            ]
        ];

        $response = $this->whatsappRegistrationController->processAccountRegistration($message, $session);

        // Verify user was created with correct data
        $user = ChatUser::where('phone_number', $this->validPhoneNumber)->first();
        $this->assertNotNull($user);
        $this->assertEquals($this->validAccountNumber, $user->account_number);
        $this->assertNull($user->pin); // WhatsApp users don't have PINs
        $this->assertTrue($user->is_verified);

        // Verify login record was created
        $this->assertDatabaseHas('chat_user_logins', [
            'chat_user_id' => $user->id,
            'session_id' => 'test-session'
        ]);
    }

    public function test_registration_prevents_duplicate_phone_numbers()
    {
        // Create existing user
        ChatUser::factory()->create([
            'phone_number' => $this->validPhoneNumber
        ]);

        // Try to register with same phone number
        $message = [
            'sender' => $this->validPhoneNumber,
            'session_id' => 'test-session',
            'content' => ''
        ];
        $session = ['state' => 'START', 'data' => []];

        $response = $this->ussdRegistrationController->handleRegistration($message, $session);
        $this->assertStringContains('already registered', $response['message']);
    }

    public function test_ussd_pin_validation()
    {
        $message = [
            'sender' => $this->validPhoneNumber,
            'session_id' => 'test-session',
            'content' => '123' // Invalid 3-digit PIN
        ];
        $session = [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => [
                'step' => RegistrationController::STATES['PIN_SETUP'],
                'account_number' => $this->validAccountNumber
            ]
        ];

        $response = $this->ussdRegistrationController->processAccountRegistration($message, $session);
        $this->assertStringContains('Invalid PIN', $response['message']);
    }

    public function test_ussd_pin_confirmation_mismatch()
    {
        $message = [
            'sender' => $this->validPhoneNumber,
            'session_id' => 'test-session',
            'content' => '5678' // Different from original PIN
        ];
        $session = [
            'state' => 'ACCOUNT_REGISTRATION',
            'data' => [
                'step' => RegistrationController::STATES['CONFIRM_PIN'],
                'account_number' => $this->validAccountNumber,
                'pin' => '1234'
            ]
        ];

        $response = $this->ussdRegistrationController->processAccountRegistration($message, $session);
        $this->assertStringContains('PINs do not match', $response['message']);
    }

    public function test_otp_expiry_and_regeneration()
    {
        $message = [
            'sender' => $this->validPhoneNumber,
            'session_id' => 'test-session',
            'content' => '123456'
        ];
        $session = [
            'state' => 'OTP_VERIFICATION',
            'data' => [
                'step' => RegistrationController::STATES['OTP_VERIFICATION'],
                'account_number' => $this->validAccountNumber,
                'otp' => '123456',
                'otp_generated_at' => now()->subMinutes(6), // Expired OTP
                'registration_reference' => 'REF123'
            ]
        ];

        $this->authService->shouldReceive('registerWithAccount')
            ->andReturn([
                'status' => GeneralStatus::SUCCESS,
                'data' => [
                    'reference' => 'REF124',
                    'otp' => '654321',
                    'requires_otp' => true
                ]
            ]);

        $response = $this->whatsappRegistrationController->processAccountRegistration($message, $session);
        $this->assertStringContains('new verification code has been sent', $response['message']);
    }

    protected function assertStringContains(string $needle, string $haystack)
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '$haystack' contains '$needle'"
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}
