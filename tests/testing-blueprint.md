# Testing Blueprint for Multi-Channel Banking System

## Test Structure Guidelines

### 1. Channel-Specific Test Organization
```
tests/Feature/
├── Authentication/
│   ├── USSDAuthenticationTest.php
│   └── WhatsAppAuthenticationTest.php
├── Registration/
│   ├── USSDRegistrationTest.php
│   └── WhatsAppRegistrationTest.php
└── Services/
    ├── BaseServiceTestTrait.php
    └── [ServiceName]Test.php
```

### 2. Test Class Template
```php
class [Feature][Channel]Test extends TestCase
{
    use RefreshDatabase;

    // 1. Properties
    private $user;
    private $sessionData = [];
    private const VALID_CREDENTIALS = [
        'phone' => '254712345678',
        'pin' => '1234'
    ];

    // 2. Setup
    protected function setUp(): void
    {
        parent::setUp();
        // Create test data
        // Mock dependencies
        // Bind instances
    }

    // 3. Test Methods
    // Group by feature flow
    public function test_successful_flow() {}
    public function test_validation_errors() {}
    public function test_error_handling() {}
}
```

### 3. Mock Setup Guidelines

#### Session Management
```php
// Track session state in test
private $sessionData = [];

// Mock adapter with session tracking
$adapter->shouldReceive('getSessionData')
    ->andReturnUsing(fn($sessionId) => $this->sessionData[$sessionId] ?? null);
$adapter->shouldReceive('updateSession')
    ->andReturnUsing(function($sessionId, $data) {
        $this->sessionData[$sessionId] = $data;
        return true;
    });
```

#### State Transitions
```php
// Set initial state
$this->sessionData['test-session'] = [
    'state' => 'INITIAL_STATE',
    'data' => ['key' => 'value']
];

// Verify state transition
$this->assertEquals('NEW_STATE', $this->sessionData['test-session']['state']);
```

### 4. Test Data Management

#### Factory Usage
```php
// Create base test data
$this->user = ChatUser::factory()->create([
    'phone_number' => self::VALID_CREDENTIALS['phone'],
    'pin' => Hash::make(self::VALID_CREDENTIALS['pin'])
]);

// Create related data
$login = ChatUserLogin::factory()
    ->for($this->user)
    ->create(['session_id' => 'test-session']);
```

### 5. Assertion Patterns

#### State Assertions
```php
// Database state
$this->assertDatabaseHas('chat_user_logins', [
    'chat_user_id' => $this->user->id,
    'session_id' => 'test-session',
    'is_active' => true
]);

// Session state
$this->assertEquals('EXPECTED_STATE', 
    $this->sessionData['test-session']['state']);

// Response content
$this->assertStringContains('Expected message', 
    $responseData['message']);
```

## Common Test Scenarios

### 1. Authentication Tests

#### USSD Authentication
```php
// Prompt: "Test USSD PIN authentication with session tracking"
public function test_ussd_pin_authentication()
{
    // 1. Initial state - Ask for PIN
    $response = $this->processMessage('');
    $this->assertPromptForPin($response);
    
    // 2. Enter PIN
    $this->setAuthenticationState();
    $response = $this->processMessage($this->validPin);
    
    // 3. Verify results
    $this->assertLoginCreated();
    $this->assertMainMenuShown($response);
}
```

#### WhatsApp Authentication
```php
// Prompt: "Test WhatsApp OTP authentication flow"
public function test_whatsapp_otp_authentication()
{
    // 1. Initial state - Send OTP
    $response = $this->processMessage('');
    $this->assertOtpSent($response);
    
    // 2. Enter OTP
    $this->setOtpVerificationState('123456');
    $response = $this->processMessage('123456');
    
    // 3. Verify results
    $this->assertLoginCreated();
    $this->assertMainMenuShown($response);
}
```

### 2. Transaction Tests

#### Balance Check
```php
// Prompt: "Test balance inquiry with authentication"
public function test_balance_inquiry()
{
    // 1. Setup authenticated session
    $this->createAuthenticatedSession();
    
    // 2. Request balance
    $response = $this->processMessage('1');
    
    // 3. Verify response
    $this->assertBalanceShown($response);
    $this->assertTransactionLogged('BALANCE_INQUIRY');
}
```

#### Money Transfer
```php
// Prompt: "Test money transfer with all validations"
public function test_money_transfer()
{
    // 1. Setup
    $this->createAuthenticatedSession();
    $this->createRecipientAccount();
    
    // 2. Enter recipient
    $response = $this->processMessage($this->recipientNumber);
    $this->assertAmountPrompt($response);
    
    // 3. Enter amount
    $this->setTransferState('AMOUNT_INPUT');
    $response = $this->processMessage('1000');
    
    // 4. Verify results
    $this->assertTransferComplete();
    $this->assertBalanceUpdated();
}
```

### 3. Error Handling Tests

#### Invalid Input
```php
// Prompt: "Test input validation with error messages"
public function test_invalid_input_handling()
{
    // 1. Setup state
    $this->setInputState();
    
    // 2. Send invalid input
    $response = $this->processMessage('invalid');
    
    // 3. Verify error handling
    $this->assertErrorMessage($response);
    $this->assertStateUnchanged();
}
```

#### Session Timeout
```php
// Prompt: "Test session timeout handling"
public function test_session_timeout()
{
    // 1. Setup expired session
    $this->createExpiredSession();
    
    // 2. Attempt action
    $response = $this->processMessage('1');
    
    // 3. Verify timeout handling
    $this->assertSessionExpired($response);
    $this->assertReloginPrompt($response);
}
```

## Implementation Guide

### 1. Authentication Flow Tests
```php
// Prompt:
"Create authentication tests for [CHANNEL] with:
- Successful login flow
- Invalid credentials handling
- Session management
- State transitions
- Error scenarios"
```

### 2. Registration Flow Tests
```php
// Prompt:
"Create registration tests for [CHANNEL] covering:
- Complete registration flow
- Validation rules
- Duplicate prevention
- OTP/PIN verification
- Error handling"
```

### 3. Transaction Flow Tests
```php
// Prompt:
"Create transaction tests for [TYPE] covering:
- Authorization checks
- Amount validation
- Balance checks
- Success scenarios
- Failure scenarios
- State management"
```

## Best Practices

1. **Isolation**: Each test should be independent and not rely on other tests' state

2. **State Management**: 
   - Track session state explicitly in tests
   - Verify state transitions
   - Clean up state between tests

3. **Mock Management**:
   - Use consistent mock setup
   - Track mock interactions
   - Verify mock expectations

4. **Error Scenarios**:
   - Test both happy and error paths
   - Verify error messages
   - Test timeout scenarios

5. **Channel Specifics**:
   - Separate channel-specific logic
   - Use channel-specific assertions
   - Test channel-specific features

## Test Implementation Steps

1. **Analysis**:
   - Identify feature requirements
   - Map out state transitions
   - List validation rules
   - Identify error scenarios

2. **Setup**:
   - Create necessary test data
   - Set up mocks
   - Initialize session state

3. **Implementation**:
   - Write happy path first
   - Add validation tests
   - Add error scenarios
   - Add edge cases

4. **Verification**:
   - Run specific test
   - Verify database state
   - Verify session state
   - Verify response format

## Efficiency Tips

1. **Targeted Testing**:
```bash
# Test specific method
php artisan test --filter test_method_name

# Test specific class
php artisan test --filter ClassName
```

2. **State Debugging**:
```php
Log::info('Current state:', [
    'session' => $this->sessionData,
    'response' => $responseData
]);
```

3. **Quick Fixes**:
- Focus on specific failing test
- Check state transitions
- Verify mock expectations
- Review database state

## Helper Traits

### 1. Session Management Trait
```php
trait ManagesTestSessions
{
    protected function setAuthenticationState(): void
    {
        $this->sessionData['test-session'] = [
            'state' => 'AUTHENTICATION',
            'data' => []
        ];
    }

    protected function setOtpVerificationState(string $otp): void
    {
        $this->sessionData['test-session'] = [
            'state' => 'OTP_VERIFICATION',
            'data' => [
                'otp' => $otp,
                'otp_generated_at' => now(),
                'is_authentication' => true
            ]
        ];
    }
}
```

### 2. Assertion Trait
```php
trait TestAssertions
{
    protected function assertPromptForPin($response): void
    {
        $this->assertStringContains('Please enter your PIN', $response['message']);
    }

    protected function assertLoginCreated(): void
    {
        $this->assertDatabaseHas('chat_user_logins', [
            'chat_user_id' => $this->user->id,
            'session_id' => 'test-session',
            'is_active' => true
        ]);
    }
}
```

### 3. Mock Setup Trait
```php
trait SetupTestMocks
{
    protected function setupChannelAdapter(): void
    {
        $adapter = $this->mock($this->getAdapterClass(), function ($mock) {
            $this->setupBasicMockExpectations($mock);
            $this->setupChannelSpecificExpectations($mock);
        });
        
        $this->app->instance($this->getAdapterClass(), $adapter);
    }
}
```

## Common Pitfalls

1. **State Leakage**:
   - Always reset state between tests
   - Use setUp/tearDown properly
   - Don't rely on database sequences

2. **Mock Complexity**:
   - Keep mock setup centralized
   - Use traits for common mock patterns
   - Document mock expectations

3. **Test Isolation**:
   - Don't share state between tests
   - Reset static properties
   - Clear cached data

4. **Performance**:
   - Use database transactions
   - Mock external services
   - Use targeted testing

5. **Maintenance**:
   - Keep tests focused
   - Use descriptive names
   - Document complex scenarios
