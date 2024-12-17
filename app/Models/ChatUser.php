<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number',
        'account_number',
        'pin',
        'is_verified'
    ];

    protected $hidden = [
        'pin'
    ];

    protected $casts = [
        'is_verified' => 'boolean'
    ];
}
