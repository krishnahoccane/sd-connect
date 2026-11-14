<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganisationAuthMethod extends Model
{
    protected $fillable = [
        'organisation_id',
        'provider_code',
        'is_enabled',
        'require_as_mfa_second_stage',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'require_as_mfa_second_stage' => 'boolean',
        ];
    }
}
