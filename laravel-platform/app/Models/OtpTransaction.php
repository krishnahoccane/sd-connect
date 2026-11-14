<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OtpTransaction extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'application_id',
        'user_id',
        'channel',
        'recipient_hash',
        'recipient_masked',
        'otp_hash',
        'purpose',
        'attempt_count',
        'max_attempts',
        'resend_count',
        'status',
        'expires_at',
        'last_sent_at',
        'verified_at',
        'ip_address',
    ];

    protected $hidden = [
        'otp_hash',
        'recipient_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryLogs(): HasMany
    {
        return $this->hasMany(OtpDeliveryLog::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(OtpAttempt::class);
    }
}
