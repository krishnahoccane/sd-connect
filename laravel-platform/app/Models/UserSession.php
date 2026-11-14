<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'sso_session_id',
        'user_id',
        'organisation_id',
        'application_id',
        'auth_provider',
        'started_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
