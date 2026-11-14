<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationAuthMethod extends Model
{
    protected $fillable = [
        'organisation_id',
        'application_id',
        'provider_code',
        'is_enabled',
        'sort_order',
        'mandatory_stages',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'mandatory_stages' => 'array',
        ];
    }
}
