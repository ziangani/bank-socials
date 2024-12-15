<?php

namespace App\Http\Controllers\Chat;

use App\Models\ChatUser;
use Illuminate\Support\Facades\Log;
use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;

class StateController extends BaseMessageController
{
    protected RegistrationController $registrationController;
    protected TransferController $transferController;
    protected BillPaymentController $billPaymentController;
    protected AccountServicesController $accountServicesController;
    protected AuthenticationController $authenticationController;
    protected MenuController $menuController;
    protected SessionController $sessionController;

    public function __construct(
        MessageAdapterInterface $messageAdapter,
        SessionManager $sessionManager,
        RegistrationController $registrationController,
        TransferController $transferController,
        BillPaymentController $billPaymentController,
        AccountServicesController $accountServicesController,
        AuthenticationController $authenticationController,
        MenuController $menuController,
        SessionController $sessionController
    ) {
        parent::__construct($messageAdapter, $sessionManager);
        $this->registrationController = $registrationController;
        $this->transferController = $transferController;
        $this->billPaymentController = $billPaymentController;
        $this->accountServicesController = $accountServicesController;
        $this->authenticationController = $authenticationController;
        $this->menuController = $menuController;
        $this->sessionController = $sessionController;
    }

    /**
     * Process message based on current state
     */
    public function processState(string $state, array $message, array $sessionData): array
    {
        // Check session timeout first
        if ($this->sessionController->isSessionExpired($sessionData)) {
            return $this->sessionController->handleSessionExpiry($message);
        }

        // Check if user is registered
        $chatUser = ChatUser::where('phone_number', $message['sender'])->first();
        
        if (!$chatUser && !in_array($state, ['WELCOME', 'REGISTRATION_INIT', 'ACCOUNT_REGISTRATION', 'HELP'])) {
            return $this->menuController->showUnregisteredMenu($message);
        }

        if (config('app.debug')) {
            Log::info('Processing state:', [
                'state' => $state,
                'message' => $message,
                'session_data' => $sessionData
            ]);
        }

        // Process OTP verification if needed
        if ($state === 'OTP_VERIFICATION') {
            return $this->authenticationController->processOTPVerification($message, $sessionData);
        }

        // Main menu states
        if (in_array($state, ['WELCOME'])) {
            return $this->menuController->processWelcomeInput($message, $sessionData);
        }

        // Help state
        if ($state === 'HELP') {
            return $this->menuController->handleHelp($message, $sessionData);
        }

        // Registration states
        if (in_array($state, ['REGISTRATION_INIT', 'ACCOUNT_REGISTRATION'])) {
            return match($state) {
                'REGISTRATION_INIT' => $this->registrationController->handleRegistration($message, $sessionData),
                'ACCOUNT_REGISTRATION' => $this->registrationController->processAccountRegistration($message, $sessionData),
                default => $this->menuController->handleUnknownState($message, $sessionData)
            };
        }

        // Check authentication for protected states
        if (!$this->authenticationController->isUserAuthenticated($message['sender'])) {
            return $this->authenticationController->initiateOTPVerification($message);
        }

        // Transfer states
        if (in_array($state, ['TRANSFER_INIT', 'INTERNAL_TRANSFER', 'BANK_TRANSFER', 'MOBILE_MONEY_TRANSFER'])) {
            return $this->handleTransferStates($state, $message, $sessionData);
        }

        // Bill payment states
        if ($state === 'BILL_PAYMENT_INIT') {
            return $this->billPaymentController->processBillPayment($message, $sessionData);
        }

        // Account services states
        if (in_array($state, ['SERVICES_INIT', 'BALANCE_INQUIRY', 'MINI_STATEMENT', 'FULL_STATEMENT', 'PIN_MANAGEMENT'])) {
            return $this->handleAccountServicesStates($state, $message, $sessionData);
        }

        return $this->menuController->handleUnknownState($message, $sessionData);
    }

    /**
     * Handle transfer states
     */
    protected function handleTransferStates(string $state, array $message, array $sessionData): array
    {
        if ($state === 'TRANSFER_INIT' && isset($message['content'])) {
            $transferMenu = $this->getMenuConfig('transfer');
            $selection = $message['content'];

            foreach ($transferMenu as $key => $option) {
                if ($selection == $key) {
                    // Update session with selected transfer type
                    $this->messageAdapter->updateSession($message['session_id'], [
                        'state' => $option['state']
                    ]);

                    // Route to appropriate transfer handler
                    return match($option['state']) {
                        'INTERNAL_TRANSFER' => $this->transferController->processInternalTransfer($message, $sessionData),
                        'BANK_TRANSFER' => $this->transferController->processBankTransfer($message, $sessionData),
                        'MOBILE_MONEY_TRANSFER' => $this->transferController->processMobileMoneyTransfer($message, $sessionData),
                        default => $this->menuController->handleUnknownState($message, $sessionData)
                    };
                }
            }

            // Invalid selection
            return $this->formatMenuResponse(
                "Invalid selection. Please select transfer type:\n\n",
                $transferMenu
            );
        }

        // Process based on current transfer state
        return match ($state) {
            'TRANSFER_INIT' => $this->transferController->handleTransfer($message, $sessionData),
            'INTERNAL_TRANSFER' => $this->transferController->processInternalTransfer($message, $sessionData),
            'BANK_TRANSFER' => $this->transferController->processBankTransfer($message, $sessionData),
            'MOBILE_MONEY_TRANSFER' => $this->transferController->processMobileMoneyTransfer($message, $sessionData),
            default => $this->menuController->handleUnknownState($message, $sessionData)
        };
    }

    /**
     * Handle account services states
     */
    protected function handleAccountServicesStates(string $state, array $message, array $sessionData): array
    {
        if ($state === 'SERVICES_INIT') {
            return $this->accountServicesController->handleAccountServices($message, $sessionData);
        }

        return match ($state) {
            'BALANCE_INQUIRY' => $this->accountServicesController->processBalanceInquiry($message, $sessionData),
            'MINI_STATEMENT' => $this->accountServicesController->processMiniStatement($message, $sessionData),
            'FULL_STATEMENT' => $this->accountServicesController->processFullStatement($message, $sessionData),
            'PIN_MANAGEMENT' => $this->accountServicesController->processPINManagement($message, $sessionData),
            default => $this->menuController->handleUnknownState($message, $sessionData)
        };
    }
}
