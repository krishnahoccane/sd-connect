<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'otp_transaction_id',
        'succeeded',
        'ip_address',
        'user_agent',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'succeeded' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }
}
