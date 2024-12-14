<?php

namespace App\Adapters\WhatsApp;

use App\Integrations\WhatsAppService;
use App\Models\WhatsAppSessions;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageSender
{
    protected WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        try {
            // First try to get business phone ID from options
            $businessPhoneId = $options['business_phone_id'] ?? null;

            // If not in options, try to get from session
            if (!$businessPhoneId) {
                $session = WhatsAppSessions::getActiveSessionBySender($recipient);
                if ($session) {
                    $sessionData = $session->data;
                    if (is_string($sessionData)) {
                        $sessionData = json_decode($sessionData, true);
                    }
                    $businessPhoneId = $sessionData['business_phone_id'] ?? null;
                }
            }

            // If still not found, use config value as fallback
            if (!$businessPhoneId) {
                $businessPhoneId = config('whatsapp.business_phone_id');
            }

            if (!$businessPhoneId) {
                throw new \Exception('Business phone ID is required');
            }

            $messageId = $options['message_id'] ?? null;

            // Check if this is an interactive message
            if (isset($options['type']) && $options['type'] === 'interactive') {
                if ($options['interactive_type'] === 'list' && isset($options['sections'])) {
                    return $this->whatsAppService->sendWelcomeMenu(
                        $businessPhoneId,
                        $recipient,
                        $messageId,
                        $message
                    );
                }
            }

            // Handle button messages
            if (isset($options['buttons'])) {
                return $this->whatsAppService->sendMessageWithButtons(
                    $businessPhoneId,
                    $recipient,
                    $messageId,
                    $message,
                    $options['buttons']
                );
            }

            // Send regular text message
            return $this->whatsAppService->sendMessage(
                $businessPhoneId,
                $recipient,
                $messageId,
                $message
            );
        } catch (\Exception $e) {
            Log::error('WhatsApp message sending error: ' . $e->getMessage());
            return false;
        }
    }

    public function markMessageAsRead(string $sender, string $messageId): void
    {
        try {
            // Try to get business phone ID from session
            $businessPhoneId = null;
            $session = WhatsAppSessions::getActiveSessionBySender($sender);
            if ($session) {
                $sessionData = $session->data;
                if (is_string($sessionData)) {
                    $sessionData = json_decode($sessionData, true);
                }
                $businessPhoneId = $sessionData['business_phone_id'] ?? null;
            }

            // If not found in session, use config value as fallback
            if (!$businessPhoneId) {
                $businessPhoneId = config('whatsapp.business_phone_id');
            }

            if (!$businessPhoneId) {
                throw new \Exception('Business phone ID is required');
            }

            $this->whatsAppService->markMessageAsRead($businessPhoneId, $messageId);
        } catch (\Exception $e) {
            Log::error('WhatsApp mark message as read error: ' . $e->getMessage());
        }
    }
}
