<?php

namespace App\Adapters;

use App\Interfaces\MessageAdapterInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class USSDMessageAdapter implements MessageAdapterInterface
{
    protected string $channel = 'ussd';
    protected const SESSION_TIMEOUT = 120; // 2 minutes
    protected const CACHE_PREFIX = 'ussd_session_';

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
            $session = Cache::get($this->getCacheKey($sessionId));
            
            if (!$session) {
                return null;
            }

            // Check session timeout
            if ((time() - $session['last_activity']) > self::SESSION_TIMEOUT) {
                $this->endSession($sessionId);
                return null;
            }

            // Update last activity
            $session['last_activity'] = time();
            Cache::put($this->getCacheKey($sessionId), $session, self::SESSION_TIMEOUT);

            return $session;
        } catch (\Exception $e) {
            Log::error('USSD session retrieval error: ' . $e->getMessage());
            return null;
        }
    }

    public function createSession(array $data): string
    {
        try {
            $sessionId = $data['session_id'];
            $session = [
                'session_id' => $sessionId,
                'phone_number' => $data['sender'],
                'state' => $data['state'] ?? 'INIT',
                'data' => $data['data'] ?? [],
                'last_activity' => time()
            ];

            Cache::put($this->getCacheKey($sessionId), $session, self::SESSION_TIMEOUT);
            return $sessionId;
        } catch (\Exception $e) {
            Log::error('USSD session creation error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateSession(string $sessionId, array $data): bool
    {
        try {
            $session = $this->getSessionData($sessionId);
            if (!$session) {
                return false;
            }

            $session['state'] = $data['state'] ?? $session['state'];
            $session['data'] = array_merge($session['data'], $data['data'] ?? []);
            $session['last_activity'] = time();

            Cache::put($this->getCacheKey($sessionId), $session, self::SESSION_TIMEOUT);
            return true;
        } catch (\Exception $e) {
            Log::error('USSD session update error: ' . $e->getMessage());
            return false;
        }
    }

    public function endSession(string $sessionId): bool
    {
        try {
            return Cache::forget($this->getCacheKey($sessionId));
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

    protected function getCacheKey(string $sessionId): string
    {
        return self::CACHE_PREFIX . $sessionId;
    }
}
