<?php

namespace App\Http\Controllers\Chat;

use App\Models\ChatUser;
use Illuminate\Support\Facades\Log;
use App\Interfaces\MessageAdapterInterface;
use App\Services\SessionManager;
use App\Adapters\WhatsAppMessageAdapter;

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
        
        if (!$chatUser && !in_array($state, ['WELCOME', 'REGISTRATION_INIT', 'ACCOUNT_REGISTRATION', 'OTP_VERIFICATION', 'HELP'])) {
            return $this->menuController->showUnregisteredMenu($message);
        }

        if (config('app.debug')) {
            Log::info('Processing state:', [
                'state' => $state,
                'message' => $message,
                'session_data' => $sessionData
            ]);
        }

        // Process authentication states
        if ($state === 'AUTHENTICATION') {
            // For USSD, use PIN verification
            if (!($this->messageAdapter instanceof WhatsAppMessageAdapter)) {
                return $this->authenticationController->processPINVerification($message, $sessionData);
            }
        }

        // Process OTP verification based on context
        if ($state === 'OTP_VERIFICATION') {
            // Check if this is a registration OTP verification
            if (isset($sessionData['data']['step']) && $sessionData['data']['step'] === 'OTP_VERIFICATION') {
                return $this->registrationController->processAccountRegistration($message, $sessionData);
            }
            // Otherwise, it's an authentication OTP verification
            return $this->authenticationController->processOTPVerification($message, $sessionData);
        }

        // Main menu states
        if (in_array($state, ['WELCOME'])) {
            // Handle "00" for returning to main menu
            if ($message['content'] === '00') {
                return $this->menuController->showMainMenu($message);
            }
            return $this->menuController->processWelcomeInput($message, $sessionData);
        }

        // Help state
        if ($state === 'HELP') {
            return $this->menuController->handleHelp($message, $sessionData);
        }

        // Registration states
        if (in_array($state, ['REGISTRATION_INIT', 'ACCOUNT_REGISTRATION'])) {
            if ($state === 'REGISTRATION_INIT') {
                return $this->registrationController->handleRegistration($message, $sessionData);
            } else {
                return $this->registrationController->processAccountRegistration($message, $sessionData);
            }
        }

        // Check authentication for protected states
        if (!$this->authenticationController->isUserAuthenticated($message['sender'])) {
            if ($this->messageAdapter instanceof WhatsAppMessageAdapter) {
                return $this->authenticationController->initiateOTPVerification($message);
            } else {
                // For USSD, set state to AUTHENTICATION and request PIN
                $this->messageAdapter->updateSession($message['session_id'], [
                    'state' => 'AUTHENTICATION',
                    'data' => []
                ]);
                return [
                    'message' => "Welcome to Social Banking\nPlease enter your PIN to continue:",
                    'type' => 'text'
                ];
            }
        }

        // Transfer states
        if (in_array($state, ['TRANSFER_INIT', 'INTERNAL_TRANSFER', 'BANK_TRANSFER', 'MOBILE_MONEY_TRANSFER'])) {
            return $this->handleTransferStates($state, $message, $sessionData);
        }

        // Bill payment states
        if (in_array($state, ['BILL_PAYMENT_INIT', 'BILL_PAYMENT'])) {
            if ($state === 'BILL_PAYMENT_INIT') {
                return $this->billPaymentController->handleBillPayment($message, $sessionData);
            } else {
                return $this->billPaymentController->processBillPayment($message, $sessionData);
            }
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
                    if ($option['state'] === 'INTERNAL_TRANSFER') {
                        return $this->transferController->processInternalTransfer($message, $sessionData);
                    } elseif ($option['state'] === 'BANK_TRANSFER') {
                        return $this->transferController->processBankTransfer($message, $sessionData);
                    } elseif ($option['state'] === 'MOBILE_MONEY_TRANSFER') {
                        return $this->transferController->processMobileMoneyTransfer($message, $sessionData);
                    } else {
                        return $this->menuController->handleUnknownState($message, $sessionData);
                    }
                }
            }

            // Invalid selection
            return $this->formatMenuResponse(
                "Invalid selection. Please select transfer type:\n\n",
                $transferMenu
            );
        }

        // Process based on current transfer state
        if ($state === 'TRANSFER_INIT') {
            return $this->transferController->handleTransfer($message, $sessionData);
        } elseif ($state === 'INTERNAL_TRANSFER') {
            return $this->transferController->processInternalTransfer($message, $sessionData);
        } elseif ($state === 'BANK_TRANSFER') {
            return $this->transferController->processBankTransfer($message, $sessionData);
        } elseif ($state === 'MOBILE_MONEY_TRANSFER') {
            return $this->transferController->processMobileMoneyTransfer($message, $sessionData);
        } else {
            return $this->menuController->handleUnknownState($message, $sessionData);
        }
    }

    /**
     * Handle account services states
     */
    protected function handleAccountServicesStates(string $state, array $message, array $sessionData): array
    {
        if ($state === 'SERVICES_INIT') {
            return $this->accountServicesController->handleAccountServices($message, $sessionData);
        }

        if ($state === 'BALANCE_INQUIRY') {
            return $this->accountServicesController->processBalanceInquiry($message, $sessionData);
        } elseif ($state === 'MINI_STATEMENT') {
            return $this->accountServicesController->processMiniStatement($message, $sessionData);
        } elseif ($state === 'FULL_STATEMENT') {
            return $this->accountServicesController->processFullStatement($message, $sessionData);
        } elseif ($state === 'PIN_MANAGEMENT') {
            return $this->accountServicesController->processPINManagement($message, $sessionData);
        } else {
            return $this->menuController->handleUnknownState($message, $sessionData);
        }
    }
}
