<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class AuthProvider extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'category',
        'is_globally_enabled',
        'supports_org_credentials',
        'requires_external_health',
        'health_status',
        'last_health_check_at',
        'default_policy',
    ];

    protected function casts(): array
    {
        return [
            'is_globally_enabled' => 'boolean',
            'supports_org_credentials' => 'boolean',
            'requires_external_health' => 'boolean',
            'last_health_check_at' => 'datetime',
            'default_policy' => 'array',
        ];
    }
}
