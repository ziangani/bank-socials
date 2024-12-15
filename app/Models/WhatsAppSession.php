<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppSession extends Model
{
    protected $fillable = [
        'session_id',
        'sender',
        'state',
        'data',
        'status'
    ];

    protected $casts = [
        'data' => 'array',
    ];
} 