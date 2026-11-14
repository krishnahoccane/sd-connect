<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthTransaction extends Model
{
    use HasPublicUuid;

    protected $table = 'oauth_transactions';

    protected $fillable = [
        'uuid',
        'organisation_id',
        'application_id',
        'passport_client_id',
        'redirect_uri',
        'state',
        'nonce',
        'code_challenge',
        'code_challenge_method',
        'requested_scopes',
        'authenticated_user_id',
        'completed_stages',
        'amr',
        'authorization_code_hash',
        'status',
        'expires_at',
        'exchanged_at',
    ];

    protected $hidden = [
        'authorization_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'requested_scopes' => 'array',
            'completed_stages' => 'array',
            'amr' => 'array',
            'expires_at' => 'datetime',
            'exchanged_at' => 'datetime',
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
        return $this->belongsTo(User::class, 'authenticated_user_id');
    }
}
