<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotUserFud extends Model
{
    protected $fillable = [
        'user_id',
        'system_value',
        'friendly_value',
        'source',
        'module',
        'type'
    ];
}
