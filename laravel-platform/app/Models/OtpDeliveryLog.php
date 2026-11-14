<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpDeliveryLog extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'otp_transaction_id',
        'organisation_id',
        'provider_code',
        'delivery_status',
        'provider_message_id',
        'http_status',
        'sanitized_response',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'sanitized_response' => 'array',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(OtpTransaction::class, 'otp_transaction_id');
    }
}
