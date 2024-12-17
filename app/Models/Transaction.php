<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference',
        'type',
        'amount',
        'fee',
        'sender',
        'recipient',
        'status',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'float',
        'fee' => 'float',
        'metadata' => AsArrayObject::class
    ];
}
