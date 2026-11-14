<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailConfiguration extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'scope',
        'organisation_id',
        'driver',
        'sender_name',
        'sender_email',
        'encrypted_transport_secrets',
        'smtp_settings',
        'otp_subject_template',
        'otp_body_template',
        'is_verified',
        'last_tested_at',
    ];

    protected $hidden = [
        'encrypted_transport_secrets',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_transport_secrets' => 'encrypted:array',
            'smtp_settings' => 'array',
            'is_verified' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
