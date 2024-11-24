<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppSessions extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'sender',
        'state',
        'data',
        'status',
        'driver'
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get active session by session ID
     */
    public static function getActiveSession(string $sessionId): ?self
    {
        return self::where('session_id', $sessionId)
            ->where('status', 'active')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Get active session by sender
     */
    public static function getActiveSessionBySender(string $sender): ?self
    {
        return self::where('sender', $sender)
            ->where('status', 'active')
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Create new session state
     */
    public static function createNewState(string $sessionId, string $sender, string $state, array $data = [], string $driver = 'whatsapp'): self
    {
        return self::create([
            'session_id' => $sessionId,
            'sender' => $sender,
            'state' => $state,
            'data' => $data,
            'status' => 'active',
            'driver' => $driver
        ]);
    }

    /**
     * End all active sessions for a sender
     */
    public static function endActiveSessions(string $sender): bool
    {
        return self::where('sender', $sender)
            ->where('status', 'active')
            ->update([
                'status' => 'ended',
                'state' => 'END'
            ]);
    }

    /**
     * Get session history
     */
    public static function getSessionHistory(string $sender, int $limit = 10): array
    {
        return self::where('sender', $sender)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($session) {
                return [
                    'session_id' => $session->session_id,
                    'state' => $session->state,
                    'status' => $session->status,
                    'driver' => $session->driver,
                    'created_at' => $session->created_at->format('Y-m-d H:i:s')
                ];
            })
            ->toArray();
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get session data value
     */
    public function getDataValue(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set session data value
     */
    public function setDataValue(string $key, $value): bool
    {
        $data = $this->data ?? [];
        $data[$key] = $value;
        return $this->update(['data' => $data]);
    }

    /**
     * Get session type-specific data
     */
    public function getTypeData(): array
    {
        return match($this->driver) {
            'whatsapp' => [
                'business_phone_id' => $this->getDataValue('business_phone_id'),
                'message_id' => $this->getDataValue('message_id'),
                'contact_name' => $this->getDataValue('contact_name')
            ],
            'ussd' => [
                'service_code' => $this->getDataValue('service_code'),
                'network' => $this->getDataValue('network')
            ],
            default => []
        };
    }

    /**
     * Scope a query to only include WhatsApp sessions
     */
    public function scopeWhatsApp($query)
    {
        return $query->where('driver', 'whatsapp');
    }

    /**
     * Scope a query to only include USSD sessions
     */
    public function scopeUssd($query)
    {
        return $query->where('driver', 'ussd');
    }

    /**
     * Scope a query to only include active sessions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include sessions in a specific state
     */
    public function scopeInState($query, string $state)
    {
        return $query->where('state', $state);
    }

    /**
     * Get the previous state for this session
     */
    public function getPreviousState(): ?string
    {
        $previousSession = self::where('session_id', $this->session_id)
            ->where('id', '<', $this->id)
            ->orderBy('id', 'desc')
            ->first();

        return $previousSession ? $previousSession->state : null;
    }

    /**
     * Check if session has gone through a specific state
     */
    public function hasPassedState(string $state): bool
    {
        return self::where('session_id', $this->session_id)
            ->where('state', $state)
            ->exists();
    }

    /**
     * Get time spent in current state
     */
    public function getStateTime(): int
    {
        return now()->diffInSeconds($this->created_at);
    }
}
