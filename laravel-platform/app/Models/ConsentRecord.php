<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class ConsentRecord extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'organisation_id',
        'application_id',
        'granted_scopes',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_scopes' => 'array',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
