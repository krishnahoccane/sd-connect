<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderConfiguration extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'scope',
        'organisation_id',
        'provider_code',
        'is_active',
        'use_platform_default',
        'encrypted_credentials',
        'public_settings',
        'health_status',
        'last_verified_at',
        'last_error_message',
    ];

    protected $hidden = [
        'encrypted_credentials',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'use_platform_default' => 'boolean',
            'encrypted_credentials' => 'encrypted:array',
            'public_settings' => 'array',
            'last_verified_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
