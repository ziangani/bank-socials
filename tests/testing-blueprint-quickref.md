# Quick Reference Guide for Testing Multi-Channel Banking System

## 1. Starting a New Test

### Command to Generate Test
```bash
php artisan make:test Feature/[Channel]/[Feature]Test
```

### Basic Test Structure
```php
use RefreshDatabase;
use ManagesTestSessions;
use TestAssertions;

class NewFeatureTest extends TestCase
{
    private $user;
    private $sessionData = [];
    
    protected function setUp(): void {
        parent::setUp();
        $this->setupTestData();
        $this->setupMocks();
    }
}
```

## 2. Common Test Patterns

### Authentication
```
Prompt: "Create authentication test for [CHANNEL] with:
- Initial state check
- Credentials verification
- Session state tracking
- Login record creation"
```

### Transactions
```
Prompt: "Create transaction test for [TYPE] with:
- Auth check
- Balance validation
- State transitions
- Success/failure handling"
```

### Registration
```
Prompt: "Create registration test for [CHANNEL] with:
- Input validation
- Duplicate checking
- Verification flow
- Record creation"
```

## 3. Quick Debug Commands

### Run Specific Test
```bash
php artisan test --filter=test_method_name
```

### Debug State
```bash
php artisan test --filter=test_name -vv
```

### Refresh Database
```bash
php artisan migrate:fresh --seed
```

## 4. Common Fixes

### State Issues
- Check session state initialization
- Verify mock expectations
- Confirm database state

### Auth Issues
- Verify user creation
- Check credentials
- Validate session state

### Transaction Issues
- Verify balance checks
- Check state transitions
- Validate records

## 5. Test Organization

### File Structure
```
tests/Feature/
├── [Channel]/
│   └── [Feature]Test.php
└── Services/
    └── [Service]Test.php
```

### Test Groups
```php
/** @group authentication */
/** @group transactions */
/** @group registration */
```

## 6. Key Assertions

### State
```php
$this->assertDatabaseHas()
$this->assertEquals()
$this->assertStringContains()
```

### Flow
```php
$this->assertStateTransition()
$this->assertResponseFormat()
$this->assertRecordCreated()
```

## 7. Mock Templates

### Channel Adapter
```php
$adapter->shouldReceive('getSessionData')
    ->andReturnUsing(fn($id) => $this->sessionData[$id] ?? null);
```

### Service
```php
$service->shouldReceive('validateData')
    ->andReturn(['status' => 'success']);
```

## 8. Common Pitfalls

### Avoid
- Shared state between tests
- Hard-coded IDs
- Missing teardown

### Prefer
- Factory data
- State tracking
- Explicit assertions
