<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationUser extends Model
{
    use HasPublicUuid;

    protected $table = 'organisation_users';

    protected $fillable = [
        'uuid',
        'organisation_id',
        'user_id',
        'membership_status',
        'employee_reference',
        'joined_at',
        'suspended_at',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->membership_status === 'active'
            && $this->organisation?->isActive() === true
            && $this->user?->isGloballyActive() === true;
    }
}
