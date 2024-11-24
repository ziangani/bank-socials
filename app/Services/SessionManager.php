<?php

namespace App\Services;

use App\Interfaces\SessionManagerInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SessionManager implements SessionManagerInterface
{
    // Session timeout in seconds (30 minutes)
    protected const SESSION_TIMEOUT = 1800;

    // Session prefix for cache keys
    protected const SESSION_PREFIX = 'social_banking_session_';

    public function create(array $data): string
    {
        try {
            $sessionId = $this->generateSessionId();
            $sessionData = [
                'id' => $sessionId,
                'channel' => $data['channel'] ?? 'unknown',
                'user_id' => $data['user_id'] ?? null,
                'state' => $data['state'] ?? 'INIT',
                'temp_data' => [],
                'created_at' => time(),
                'last_activity' => time()
            ];

            $this->saveSession($sessionId, $sessionData);
            return $sessionId;
        } catch (\Exception $e) {
            Log::error('Session creation error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function get(string $sessionId): ?array
    {
        try {
            $session = $this->getSession($sessionId);
            if (!$session) {
                return null;
            }

            // Update last activity
            $session['last_activity'] = time();
            $this->saveSession($sessionId, $session);

            return $session;
        } catch (\Exception $e) {
            Log::error('Session retrieval error: ' . $e->getMessage());
            return null;
        }
    }

    public function update(string $sessionId, array $data): bool
    {
        try {
            $session = $this->getSession($sessionId);
            if (!$session) {
                return false;
            }

            // Update session data
            $session = array_merge($session, $data);
            $session['last_activity'] = time();

            $this->saveSession($sessionId, $session);
            return true;
        } catch (\Exception $e) {
            Log::error('Session update error: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(string $sessionId): bool
    {
        try {
            return Cache::forget($this->getCacheKey($sessionId));
        } catch (\Exception $e) {
            Log::error('Session deletion error: ' . $e->getMessage());
            return false;
        }
    }

    public function isValid(string $sessionId): bool
    {
        try {
            $session = $this->getSession($sessionId);
            if (!$session) {
                return false;
            }

            // Check session timeout
            if ((time() - $session['last_activity']) > self::SESSION_TIMEOUT) {
                $this->delete($sessionId);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Session validation error: ' . $e->getMessage());
            return false;
        }
    }

    public function getState(string $sessionId): ?string
    {
        try {
            $session = $this->getSession($sessionId);
            return $session['state'] ?? null;
        } catch (\Exception $e) {
            Log::error('Get state error: ' . $e->getMessage());
            return null;
        }
    }

    public function setState(string $sessionId, string $state): bool
    {
        try {
            return $this->update($sessionId, ['state' => $state]);
        } catch (\Exception $e) {
            Log::error('Set state error: ' . $e->getMessage());
            return false;
        }
    }

    public function setTemp(string $sessionId, string $key, $value): bool
    {
        try {
            $session = $this->getSession($sessionId);
            if (!$session) {
                return false;
            }

            $session['temp_data'][$key] = $value;
            return $this->update($sessionId, ['temp_data' => $session['temp_data']]);
        } catch (\Exception $e) {
            Log::error('Set temp data error: ' . $e->getMessage());
            return false;
        }
    }

    public function getTemp(string $sessionId, string $key)
    {
        try {
            $session = $this->getSession($sessionId);
            return $session['temp_data'][$key] ?? null;
        } catch (\Exception $e) {
            Log::error('Get temp data error: ' . $e->getMessage());
            return null;
        }
    }

    public function clearTemp(string $sessionId): bool
    {
        try {
            return $this->update($sessionId, ['temp_data' => []]);
        } catch (\Exception $e) {
            Log::error('Clear temp data error: ' . $e->getMessage());
            return false;
        }
    }

    protected function generateSessionId(): string
    {
        return uniqid('sess_', true);
    }

    protected function getCacheKey(string $sessionId): string
    {
        return self::SESSION_PREFIX . $sessionId;
    }

    protected function getSession(string $sessionId): ?array
    {
        return Cache::get($this->getCacheKey($sessionId));
    }

    protected function saveSession(string $sessionId, array $data): void
    {
        Cache::put(
            $this->getCacheKey($sessionId),
            $data,
            now()->addSeconds(self::SESSION_TIMEOUT)
        );
    }
}
