<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasPublicUuid;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'primary_email',
        'email_verified_at',
        'primary_phone',
        'phone_verified_at',
        'password',
        'password_changed_at',
        'status',
        'is_super_admin',
        'mfa_enabled',
        'failed_login_count',
        'locked_until',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'mfa_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganisationUser::class);
    }

    public function isGloballyActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->locked_until !== null && $this->locked_until->isFuture()) {
            return false;
        }

        return true;
    }

    public function hasActiveMembershipIn(int $organisationId): bool
    {
        if (!$this->isGloballyActive()) {
            return false;
        }

        return $this->memberships()
            ->where('organisation_id', $organisationId)
            ->where('membership_status', 'active')
            ->exists();
    }

    public function getEmailForPasswordReset(): string
    {
        return (string) $this->primary_email;
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->primary_email;
    }
}
