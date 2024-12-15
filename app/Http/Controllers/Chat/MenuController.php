<?php

namespace App\Http\Controllers\Chat;

use App\Models\ChatUser;

class MenuController extends BaseMessageController
{
    /**
     * Show main menu for authenticated users
     */
    public function showMainMenu(array $message): array
    {
        $contactName = $message['contact_name'] ?? 'there';
        $welcomeText = "Hello {$contactName}! 👋\n\nPlease select an option from the menu below:\n";

        // Get the appropriate menu based on user registration status
        $chatUser = ChatUser::where('phone_number', $message['sender'])->first();
        $menuConfig = $chatUser ? 'main' : 'unregistered';
        $menu = $this->getMenuConfig($menuConfig);
        
        // Add menu options to the message text
        foreach ($menu as $key => $option) {
            $welcomeText .= "{$key}. {$option['text']}\n";
        }

        $welcomeText .= "\nTo return to this menu at any time, reply with 00.\nTo exit at any time, reply with 000.";

        return [
            'message' => $welcomeText,
            'type' => 'text'
        ];
    }

    /**
     * Show menu for unregistered users
     */
    public function showUnregisteredMenu(array $message): array
    {
        $welcomeText = "Welcome to our banking service! 👋\n\nPlease select an option:\n\n";
        
        $unregisteredMenu = $this->getMenuConfig('unregistered');
        foreach ($unregisteredMenu as $key => $option) {
            $welcomeText .= "{$key}. {$option['text']}\n";
        }
        
        $welcomeText .= "\nReply with the number of your choice.";

        return [
            'message' => $welcomeText,
            'type' => 'text'
        ];
    }

    /**
     * Show account services menu
     */
    public function showAccountServicesMenu(array $message): array
    {
        $servicesMenu = $this->getMenuConfig('account_services');
        $menuText = "Account Services Menu:\n\n";
        
        foreach ($servicesMenu as $serviceKey => $serviceOption) {
            $menuText .= "{$serviceKey}. {$serviceOption['text']}\n";
        }
        
        $menuText .= "\nReply with the number of your choice.\n";
        $menuText .= "Reply with 00 for main menu or 000 to exit.";

        return [
            'message' => $menuText,
            'type' => 'text'
        ];
    }

    /**
     * Process welcome menu input
     */
    public function processWelcomeInput(array $message, array $sessionData): array
    {
        $input = $message['content'];
        
        // Check if user is registered
        $chatUser = ChatUser::where('phone_number', $message['sender'])->first();
        $menuConfig = $chatUser ? 'main' : 'unregistered';
        $menu = $this->getMenuConfig($menuConfig);

        foreach ($menu as $key => $option) {
            if ($input == $key || strtolower($input) == strtolower($option['text'])) {
                // Update session with selected option
                $this->messageAdapter->updateSession($message['session_id'], [
                    'state' => $option['state'],
                    'data' => [
                        'last_message' => $input,
                        'selected_option' => $key
                    ]
                ]);

                return match ($option['state']) {
                    'REGISTRATION_INIT' => app(RegistrationController::class)->handleRegistration($message, $sessionData),
                    'HELP' => $this->handleHelp($message, $sessionData),
                    'TRANSFER_INIT' => app(TransferController::class)->handleTransfer($message, $sessionData),
                    'BILL_PAYMENT_INIT' => app(BillPaymentController::class)->handleBillPayment($message, $sessionData),
                    'SERVICES_INIT' => $this->showAccountServicesMenu($message),
                    default => $this->handleUnknownState($message, $sessionData)
                };
            }
        }

        // Show appropriate menu again for invalid input
        return $chatUser ? $this->showMainMenu($message) : $this->showUnregisteredMenu($message);
    }

    /**
     * Handle help menu option
     */
    public function handleHelp(array $message, array $sessionData): array
    {
        $helpText = "Welcome to our Banking Service Help! 🤝\n\n";
        $helpText .= "Here's how to use our service:\n\n";
        $helpText .= "1. Registration:\n";
        $helpText .= "   - Select 'Register' from the menu\n";
        $helpText .= "   - Enter your 10-digit account number\n";
        $helpText .= "   - Set up a 4-digit PIN\n";
        $helpText .= "   - Verify with OTP\n\n";
        $helpText .= "2. Login:\n";
        $helpText .= "   - Verify with OTP each session\n";
        $helpText .= "   - For USSD, use your PIN\n\n";
        $helpText .= "3. Navigation:\n";
        $helpText .= "   - Use menu numbers to select options\n";
        $helpText .= "   - Type 00 to return to main menu\n";
        $helpText .= "   - Type 000 to exit\n\n";
        $helpText .= "Reply with 00 to return to the main menu.";

        return [
            'message' => $helpText,
            'type' => 'text'
        ];
    }

    /**
     * Handle unknown state
     */
    protected function handleUnknownState(array $message, array $sessionData): array
    {
        return [
            'message' => "Sorry, something went wrong. Please try again.\n\nReply with 00 to return to main menu.",
            'type' => 'text'
        ];
    }
}
