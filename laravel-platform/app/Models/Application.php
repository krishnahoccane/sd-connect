<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'name',
        'slug',
        'twsd_subdomain',
        'custom_domain',
        'custom_domain_verified',
        'app_url',
        'description',
        'logo_path',
        'application_type',
        'status',
        'archived_at',
        'jit_provisioning_override',
        'require_pkce',
        'allowed_scopes',
        'allowed_grant_types',
        'access_token_ttl_minutes',
        'refresh_token_ttl_days',
        'access_policy',
        'branding_overrides',
    ];

    protected function casts(): array
    {
        return [
            'custom_domain_verified' => 'boolean',
            'archived_at' => 'datetime',
            'require_pkce' => 'boolean',
            'allowed_scopes' => 'array',
            'allowed_grant_types' => 'array',
            'access_policy' => 'array',
            'branding_overrides' => 'array',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function oauthClientRegistry(): HasOne
    {
        return $this->hasOne(OAuthClientRegistry::class);
    }

    public function redirectUris(): HasMany
    {
        return $this->hasMany(OAuthRedirectUri::class);
    }

    public function authMethods(): HasMany
    {
        return $this->hasMany(ApplicationAuthMethod::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->organisation?->isActive() === true;
    }
}
