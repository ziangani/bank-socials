<?php

namespace Tests\Feature\Authentication;

use Tests\TestCase;
use App\Models\ChatUser;
use App\Models\ChatUserLogin;
use App\Services\AuthenticationService;
use App\Services\SessionManager;
use App\Adapters\WhatsAppMessageAdapter;
use App\Adapters\USSDMessageAdapter;
use App\Http\Controllers\Chat\ChatController;
use App\Http\Controllers\Chat\AuthenticationController;
use App\Http\Controllers\Chat\MenuController;
use App\Http\Controllers\Chat\StateController;
use App\Http\Controllers\Chat\SessionController;
use App\Http\Controllers\Chat\RegistrationController;
use App\Common\GeneralStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Mockery;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    private ChatUser $user;
    private string $validPin = '1234';
    private $sessionData = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = ChatUser::factory()->create([
            'phone_number' => '254712345678',
            'account_number' => '1234567890',
            'pin' => Hash::make($this->validPin),
            'is_verified' => true
        ]);

        // Create common mocks
        $sessionManager = $this->mock(SessionManager::class, function ($mock) {
            $mock->shouldReceive('isSessionExpired')->andReturn(false);
            $mock->shouldReceive('endSession')->andReturn(true);
        });

        $authService = $this->mock(AuthenticationService::class);
        
        $menuController = $this->mock(MenuController::class, function ($mock) {
            $mock->shouldReceive('showMainMenu')->andReturn([
                'message' => 'Welcome to main menu',
                'type' => 'text'
            ]);
            $mock->shouldReceive('showUnregisteredMenu')->andReturn([
                'message' => 'Welcome to Social Banking\n1. Register\n2. Help',
                'type' => 'text'
            ]);
        });

        $sessionController = $this->mock(SessionController::class, function ($mock) {
            $mock->shouldReceive('isSessionExpired')->andReturn(false);
            $mock->shouldReceive('handleSessionExpiry')->andReturn([
                'message' => 'Session expired',
                'type' => 'text'
            ]);
        });

        $stateController = $this->mock(StateController::class, function ($mock) {
            $mock->shouldReceive('processState')->andReturnUsing(function ($state, $message, $sessionData) {
                Log::info('Processing state in StateController', [
                    'state' => $state,
                    'message' => $message,
                    'session' => $sessionData
                ]);

                if ($state === 'AUTHENTICATION') {
                    return $this->app->make(AuthenticationController::class)
                        ->processPINVerification($message, $sessionData);
                }

                if ($state === 'OTP_VERIFICATION') {
                    return $this->app->make(AuthenticationController::class)
                        ->processOTPVerification($message, $sessionData);
                }

                return [
                    'message' => 'Invalid state',
                    'type' => 'text'
                ];
            });
        });

        $registrationController = $this->mock(RegistrationController::class);
        
        // Set up USSD adapter
        $ussdAdapter = $this->mock(USSDMessageAdapter::class, function ($mock) {
            $mock->shouldReceive('parseIncomingMessage')->andReturnUsing(function($data) {
                return [
                    'sender' => $data['sender'],
                    'session_id' => $data['session_id'],
                    'content' => $data['content'],
                    'message_id' => 'msg_' . time()
                ];
            });
            $mock->shouldReceive('markMessageAsRead')->byDefault();
            $mock->shouldReceive('isMessageProcessed')->andReturn(false);
            $mock->shouldReceive('createSession')->andReturnUsing(function($data) {
                $this->sessionData[$data['session_id']] = [
                    'state' => 'AUTHENTICATION',
                    'data' => []
                ];
                return $data['session_id'];
            });
            $mock->shouldReceive('getSessionData')->andReturnUsing(function($sessionId) {
                Log::info('Getting session data for USSD', [
                    'session_id' => $sessionId,
                    'data' => $this->sessionData[$sessionId] ?? null
                ]);
                return $this->sessionData[$sessionId] ?? null;
            });
            $mock->shouldReceive('updateSession')->andReturnUsing(function($sessionId, $data) {
                Log::info('Updating USSD session', [
                    'session_id' => $sessionId,
                    'data' => $data
                ]);
                $this->sessionData[$sessionId] = $data;
                return true;
            });
            $mock->shouldReceive('markMessageAsProcessed')->byDefault();
            $mock->shouldReceive('sendMessage')->byDefault();
            $mock->shouldReceive('formatOutgoingMessage')->andReturnArg(0);
            $mock->shouldReceive('endSession')->andReturn(true);
            $mock->shouldReceive('instanceOf')->with(WhatsAppMessageAdapter::class)->andReturn(false);
            $mock->shouldReceive('formatButtons')->andReturn([]);
        });

        // Set up WhatsApp adapter
        $whatsappAdapter = $this->mock(WhatsAppMessageAdapter::class, function ($mock) {
            $mock->shouldReceive('parseIncomingMessage')->andReturnUsing(function($data) {
                return [
                    'sender' => $data['sender'],
                    'session_id' => $data['session_id'],
                    'content' => $data['content'],
                    'message_id' => 'msg_' . time()
                ];
            });
            $mock->shouldReceive('markMessageAsRead')->byDefault();
            $mock->shouldReceive('isMessageProcessed')->andReturn(false);
            $mock->shouldReceive('createSession')->andReturnUsing(function($data) {
                $this->sessionData[$data['session_id']] = [
                    'state' => 'OTP_VERIFICATION',
                    'data' => [
                        'otp' => '123456',
                        'otp_generated_at' => now(),
            $mock->shouldReceive('formatOutgoingMessage')->andReturnArg(0);
            $mock->shouldReceive('endSession')->andReturn(true);
            $mock->shouldReceive('instanceOf')->with(WhatsAppMessageAdapter::class)->andReturn(true);
            $mock->shouldReceive('formatButtons')->andReturn([]);
        });

        // Mock AuthenticationController
        $authController = $this->mock(AuthenticationController::class, function ($mock) {
            $mock->shouldReceive('initiateOTPVerification')->andReturnUsing(function($message) {
                // Store OTP in session
                $otp = '123456';
                $this->sessionData[$message['session_id']] = [
                    'state' => 'OTP_VERIFICATION',
                    'data' => [
                        'otp' => $otp,
                        'otp_generated_at' => now(),
                        'is_authentication' => true
                    ]
                ];

                return [
                    'message' => "Please enter the 6-digit OTP sent to your number via SMS.\n\nTest OTP: $otp",
                    'type' => 'text'
                ];
            });
            $mock->shouldReceive('handleLogout')->andReturnUsing(function($message) {
                // Deactivate any active logins
                ChatUserLogin::where('phone_number', $message['sender'])
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                return response()->json([
                    'message' => 'Thank you for using Social Banking. Goodbye! 👋',
                    'type' => 'text',
                    'end_session' => true
                ]);
            });
            $mock->shouldReceive('processOTPVerification')->andReturnUsing(function ($message, $sessionData) {
                Log::info('Processing OTP verification in AuthController', [
                    'message' => $message,
                    'session' => $sessionData
                ]);

                $storedOtp = $sessionData['data']['otp'] ?? null;
                if ($message['content'] === $storedOtp) {
                    // Create real login record on successful OTP verification
                    $login = ChatUserLogin::createLogin(
                        ChatUser::where('phone_number', $message['sender'])->first(),
                        $message['session_id']
                    );

                    Log::info('Created login record in AuthController', ['login' => $login->toArray()]);

                    return [
                        'message' => 'Welcome to main menu',
                        'type' => 'text'
                    ];
                }

                return [
                    'message' => 'Invalid OTP',
                    'type' => 'text'
                ];
            });
            $mock->shouldReceive('processPINVerification')->andReturnUsing(function ($message, $sessionData) {
                Log::info('Processing PIN verification in AuthController', [
                    'message' => $message,
                    'session' => $sessionData
                ]);

                $chatUser = ChatUser::where('phone_number', $message['sender'])->first();
                if (Hash::check($message['content'], $chatUser->pin)) {
                    // Create real login record on successful PIN verification
                    $login = ChatUserLogin::createLogin($chatUser, $message['session_id']);
                    Log::info('Created login record in AuthController', ['login' => $login->toArray()]);

                    return [
                        'message' => 'Welcome to main menu',
                        'type' => 'text'
                    ];
                }

                return [
                    'message' => 'Invalid PIN',
                    'type' => 'text'
                ];
            });
        });

        // Bind instances to container
        $this->app->instance(USSDMessageAdapter::class, $ussdAdapter);
        $this->app->instance(WhatsAppMessageAdapter::class, $whatsappAdapter);
        $this->app->instance(SessionManager::class, $sessionManager);
        $this->app->instance(AuthenticationService::class, $authService);
        $this->app->instance(MenuController::class, $menuController);
        $this->app->instance(StateController::class, $stateController);
        $this->app->instance(SessionController::class, $sessionController);
        $this->app->instance(RegistrationController::class, $registrationController);
        $this->app->instance(AuthenticationController::class, $authController);
    }

    public function test_ussd_authentication_flow()
    {
        Log::info('Starting USSD authentication test');

        // Step 1: Start Session - Should ask for PIN
        $request = new Request([
            'sender' => $this->user->phone_number,
            'session_id' => 'test-session',
            'content' => ''
        ]);

        /** @var ChatController $controller */
        $controller = $this->app->make(ChatController::class, [
            'messageAdapter' => $this->app->make(USSDMessageAdapter::class)
        ]);

        $response = $controller->processMessage($request);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertStringContains('Welcome to Social Banking', $responseData['message']);
        $this->assertStringContains('Please enter your PIN', $responseData['message']);

        // Step 2: Enter PIN
        $request = new Request([
            'sender' => $this->user->phone_number,
            'session_id' => 'test-session',
            'content' => $this->validPin
        ]);

        Log::info('Sending PIN', ['pin' => $this->validPin]);

        $response = $controller->processMessage($request);
        $responseData = json_decode($response->getContent(), true);

        Log::info('Response after PIN', ['response' => $responseData]);

        // Verify login was created
        $this->assertDatabaseHas('chat_user_logins', [
            'chat_user_id' => $this->user->id,
            'session_id' => 'test-session',
            'phone_number' => $this->user->phone_number,
            'is_active' => true
        ]);

        // Verify main menu is shown
        $this->assertStringContains('Welcome to main menu', $responseData['message']);
    }

    public function test_whatsapp_authentication_flow()
    {
        Log::info('Starting WhatsApp authentication test');

        // Step 1: Start Session - Should initiate OTP
        $request = new Request([
            'sender' => $this->user->phone_number,
            'session_id' => 'test-session',
            'content' => ''
        ]);

        /** @var ChatController $controller */
        $controller = $this->app->make(ChatController::class, [
            'messageAdapter' => $this->app->make(WhatsAppMessageAdapter::class)
        ]);

        $response = $controller->processMessage($request);
        $responseData = json_decode($response->getContent(), true);
        
        $this->assertStringContains('Please enter the 6-digit OTP', $responseData['message']);

        // Step 2: Enter OTP
        $request = new Request([
            'sender' => $this->user->phone_number,
            'session_id' => 'test-session',
            'content' => '123456'
        ]);

        Log::info('Sending OTP', ['otp' => '123456']);

        $response = $controller->processMessage($request);
        $responseData = json_decode($response->getContent(), true);

        Log::info('Response after OTP', ['response' => $responseData]);

        // Verify login was created
        $this->assertDatabaseHas('chat_user_logins', [
            'chat_user_id' => $this->user->id,
            'session_id' => 'test-session',
            'phone_number' => $this->user->phone_number,
            'is_active' => true
        ]);

        // Verify main menu is shown
        $this->assertStringContains('Welcome to main menu', $responseData['message']);
    }

    public function test_logout_flow()
    {
        // Create active login
        ChatUserLogin::createLogin($this->user, 'test-session');

        // Send logout command
        $request = new Request([
            'sender' => $this->user->phone_number,
            'session_id' => 'test-session',
            'content' => '000'
        ]);

        /** @var ChatController $controller */
        $controller = $this->app->make(ChatController::class, [
            'messageAdapter' => $this->app->make(USSDMessageAdapter::class)
        ]);

        $response = $controller->processMessage($request);
        $responseData = json_decode($response->getContent(), true);

        // Verify logout message
        $this->assertStringContains('Thank you for using', $responseData['message']);

        // Verify login was deactivated
        $this->assertDatabaseMissing('chat_user_logins', [
            'chat_user_id' => $this->user->id,
            'session_id' => 'test-session',
            'is_active' => true
        ]);
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
