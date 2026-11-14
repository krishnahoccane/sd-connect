<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthRefreshTokenFamily extends Model
{
    use HasPublicUuid;

    protected $table = 'oauth_refresh_token_families';

    protected $fillable = [
        'uuid',
        'family_uuid',
        'organisation_id',
        'application_id',
        'user_id',
        'passport_client_id',
        'passport_token_id',
        'token_hash',
        'scopes',
        'amr',
        'status',
        'expires_at',
        'rotated_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'amr' => 'array',
            'expires_at' => 'datetime',
            'rotated_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
