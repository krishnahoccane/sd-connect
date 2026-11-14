<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasPublicUuid;

    public $timestamps = false;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'actor_user_id',
        'actor_role',
        'action',
        'target_type',
        'target_uuid',
        'before_state',
        'after_state',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
