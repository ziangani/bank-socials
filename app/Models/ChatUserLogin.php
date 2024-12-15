<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ChatUserLogin extends Model
{
    protected $fillable = [
        'chat_user_id',
        'session_id',
        'phone_number',
        'authenticated_at',
        'expires_at',
        'is_active'
    ];

    protected $casts = [
        'authenticated_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    public function chatUser()
    {
        return $this->belongsTo(ChatUser::class);
    }

    public static function getActiveLogin(string $phoneNumber): ?self
    {
        return static::where('phone_number', $phoneNumber)
            ->where('is_active', true)
            ->where('expires_at', '>', Carbon::now())
            ->latest()
            ->first();
    }

    public static function createLogin(ChatUser $user, string $sessionId): self
    {
        // Deactivate any existing active logins
        static::where('phone_number', $user->phone_number)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Create new login
        return static::create([
            'chat_user_id' => $user->id,
            'session_id' => $sessionId,
            'phone_number' => $user->phone_number,
            'authenticated_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes(30), // 30 minute session
            'is_active' => true
        ]);
    }

    public function isValid(): bool
    {
        return $this->is_active && $this->expires_at->isFuture();
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }
}
