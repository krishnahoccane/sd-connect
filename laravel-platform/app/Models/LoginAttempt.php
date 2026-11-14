<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    use HasPublicUuid;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'application_id',
        'user_id',
        'identifier_masked',
        'provider_code',
        'outcome',
        'failure_reason',
        'ip_address',
        'user_agent',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
        ];
    }
}
