<?php

namespace App\Adapters;

use App\Interfaces\MessageAdapterInterface;
use App\Models\WhatsAppSessions;
use Illuminate\Support\Facades\Log;

class USSDMessageAdapter implements MessageAdapterInterface
{
    protected string $channel = 'ussd';

    public function parseIncomingMessage(array $request): array
    {
        try {
            return [
                'session_id' => $request['sessionId'] ?? null,
                'message_id' => $request['sessionId'] ?? null, // USSD uses session ID as message ID
                'sender' => $request['phoneNumber'] ?? null,
                'recipient' => $request['serviceCode'] ?? '*123#',
                'type' => 'text',
                'content' => $request['text'] ?? '',
                'timestamp' => time(),
                'raw_data' => $request
            ];
        } catch (\Exception $e) {
            Log::error('USSD message parsing error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function formatOutgoingMessage(array $response): array
    {
        try {
            return [
                'message' => $response['message'],
                'type' => $response['end_session'] ? 'END' : 'CON'
            ];
        } catch (\Exception $e) {
            Log::error('USSD message formatting error: ' . $e->getMessage());
            return [
                'message' => 'System error. Please try again.',
                'type' => 'END'
            ];
        }
    }

    public function getSessionData(string $sessionId): ?array
    {
        try {
            $session = WhatsAppSessions::where('session_id', $sessionId)
                ->where('status', 'active')
                ->orderBy('id', 'desc')
                ->first();

            if (!$session) {
                // Try to find by phone number (for USSD continuity)
                $session = WhatsAppSessions::where('sender', $sessionId)
                    ->where('status', 'active')
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$session) {
                return null;
            }

            return [
                'id' => $session->id,
                'session_id' => $session->session_id,
                'sender' => $session->sender,
                'state' => $session->state,
                'data' => json_decode($session->data, true),
                'created_at' => $session->created_at,
                'updated_at' => $session->updated_at
            ];
        } catch (\Exception $e) {
            Log::error('USSD session retrieval error: ' . $e->getMessage());
            return null;
        }
    }

    public function createSession(array $data): string
    {
        try {
            $session = new WhatsAppSessions();
            $session->session_id = $data['session_id'];
            $session->sender = $data['sender'];
            $session->state = $data['state'] ?? 'INIT';
            $session->data = json_encode($data['data'] ?? []);
            $session->status = 'active';
            $session->driver = $this->channel;
            $session->save();

            return $session->session_id;
        } catch (\Exception $e) {
            Log::error('USSD session creation error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateSession(string $sessionId, array $data): bool
    {
        try {
            $session = WhatsAppSessions::where('session_id', $sessionId)
                ->where('status', 'active')
                ->orderBy('id', 'desc')
                ->first();

            if (!$session) {
                return false;
            }

            // Create new session record for state change
            $newSession = new WhatsAppSessions();
            $newSession->session_id = $sessionId;
            $newSession->sender = $session->sender;
            $newSession->state = $data['state'] ?? $session->state;
            $newSession->data = json_encode($data['data'] ?? json_decode($session->data, true));
            $newSession->status = 'active';
            $newSession->driver = $this->channel;
            $newSession->save();

            return true;
        } catch (\Exception $e) {
            Log::error('USSD session update error: ' . $e->getMessage());
            return false;
        }
    }

    public function endSession(string $sessionId): bool
    {
        try {
            return WhatsAppSessions::where('session_id', $sessionId)
                ->where('status', 'active')
                ->update(['status' => 'ended', 'state' => 'END']);
        } catch (\Exception $e) {
            Log::error('USSD session end error: ' . $e->getMessage());
            return false;
        }
    }

    public function isMessageProcessed(string $messageId): bool
    {
        // USSD messages are processed in real-time, no need to check
        return false;
    }

    public function markMessageAsProcessed(string $messageId): bool
    {
        // USSD messages are processed in real-time, no need to mark
        return true;
    }

    public function getUserIdentifier(array $request): string
    {
        return $request['phoneNumber'] ?? '';
    }

    public function getMessageContent(array $request): string
    {
        return $request['text'] ?? '';
    }

    public function getMessageType(array $request): string
    {
        return 'text'; // USSD only supports text
    }

    public function formatMenuOptions(array $options): array
    {
        $menu = '';
        $index = 1;
        foreach ($options as $option) {
            $menu .= "{$index}. {$option}\n";
            $index++;
        }
        return ['menu' => trim($menu)];
    }

    public function formatButtons(array $buttons): array
    {
        // USSD doesn't support buttons, convert to numbered menu
        return $this->formatMenuOptions($buttons);
    }

    public function sendMessage(string $recipient, string $message, array $options = []): bool
    {
        // USSD responses are handled synchronously through formatOutgoingMessage
        return true;
    }

    public function markMessageAsRead(string $sender, string $messageId): void
    {
        // USSD doesn't support read receipts, no-op implementation
    }
}
