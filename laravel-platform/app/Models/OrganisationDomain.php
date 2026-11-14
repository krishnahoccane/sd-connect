<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationDomain extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'domain',
        'is_primary',
        'is_verified',
        'verification_token',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
