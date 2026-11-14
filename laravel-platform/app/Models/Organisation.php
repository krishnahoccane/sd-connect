<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organisation extends Model
{
    use HasPublicUuid;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'status',
        'jit_provisioning_mode',
        'mfa_required',
        'session_lifetime_minutes',
        'password_policy',
        'otp_policy',
        'suspension_reason',
        'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'mfa_required' => 'boolean',
            'password_policy' => 'array',
            'otp_policy' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    public function domains(): HasMany
    {
        return $this->hasMany(OrganisationDomain::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganisationUser::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function authMethods(): HasMany
    {
        return $this->hasMany(OrganisationAuthMethod::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
