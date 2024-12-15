<?php

namespace App\Adapters\WhatsApp;

use App\Models\WhatsAppSessions;
use Illuminate\Support\Facades\Log;

class WhatsAppSessionManager
{
    protected string $channel = 'whatsapp';

    public function getSessionData(string $sessionId): ?array
    {
        try {
            $session = WhatsAppSessions::getActiveSession($sessionId);

            if (!$session) {
                // Try to find by sender
                $session = WhatsAppSessions::getActiveSessionBySender($sessionId);
            }

            if (!$session) {
                return null;
            }

            return [
                'id' => $session->id,
                'session_id' => $session->session_id,
                'sender' => $session->sender,
                'state' => $session->state,
                'data' => $session->data ?? [],
                'created_at' => $session->created_at,
                'updated_at' => $session->updated_at
            ];
        } catch (\Exception $e) {
            Log::error('WhatsApp session retrieval error: ' . $e->getMessage());
            return null;
        }
    }

    public function createSession(array $data): string
    {
        try {
            // End any existing active sessions for this sender
            WhatsAppSessions::endActiveSessions($data['sender']);

            // Safely get message_id from nested data
            $messageId = null;
            if (isset($data['data']) && is_array($data['data']) && isset($data['data']['message_id'])) {
                $messageId = $data['data']['message_id'];
            }

            // Create new session with safely merged data
            $sessionData = array_merge($data['data'] ?? [], [
                'business_phone_id' => $data['business_phone_id'] ?? null,
                'message_id' => $messageId,
                'contact_name' => $data['contact_name'] ?? null,
                'authenticated_at' => null,
                'otp_verified' => false
            ]);

            // Create new session state
            $session = WhatsAppSessions::createNewState(
                $data['session_id'],
                $data['sender'],
                $data['state'] ?? 'INIT',
                $sessionData,
                $this->channel
            );

            return $session->session_id;
        } catch (\Exception $e) {
            Log::error('WhatsApp session creation error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function updateSession(string $sessionId, array $data): bool
    {
        try {
            $currentSession = WhatsAppSessions::getActiveSession($sessionId);

            if (!$currentSession) {
                return false;
            }

            // Get current session data
            $currentData = $currentSession->data ?? [];

            // If state is changing, only preserve essential data
            if (isset($data['state']) && $data['state'] !== $currentSession->state) {
                // Only keep essential session data when transitioning states
                $preservedData = array_intersect_key($currentData, array_flip([
                    'session_id',
                    'message_id',
                    'sender',
                    'business_phone_id',
                    'contact_name',
                    'authenticated_at',
                    'otp_verified',
                    'authenticated_user'
                ]));
                
                // Use only the new state data plus preserved essential data
                $mergedData = array_merge(
                    $preservedData,
                    $data['data'] ?? []
                );
            } else {
                // If staying in same state, merge with current data
                $mergedData = array_merge(
                    $currentData,
                    $data['data'] ?? []
                );
            }

            // Create new session state
            WhatsAppSessions::createNewState(
                $sessionId,
                $currentSession->sender,
                $data['state'] ?? $currentSession->state,
                $mergedData,
                $this->channel
            );

            return true;
        } catch (\Exception $e) {
            Log::error('WhatsApp session update error: ' . $e->getMessage());
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
            Log::error('WhatsApp session end error: ' . $e->getMessage());
            return false;
        }
    }
}
