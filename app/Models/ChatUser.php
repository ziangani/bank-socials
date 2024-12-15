<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatUser extends Model
{
    protected $fillable = [
        'phone_number',
        'account_number',
        'pin',
        'is_verified',
        'last_otp_sent_at'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'last_otp_sent_at' => 'datetime'
    ];
}
