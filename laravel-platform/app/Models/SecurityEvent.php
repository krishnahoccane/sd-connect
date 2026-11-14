<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class SecurityEvent extends Model
{
    use HasPublicUuid;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'user_id',
        'event_type',
        'severity',
        'ip_address',
        'context',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
