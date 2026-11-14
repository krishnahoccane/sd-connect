<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SsoSession extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'session_token_hash',
        'amr',
        'authenticated_at',
        'last_activity_at',
        'expires_at',
        'revoked_at',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = [
        'session_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'amr' => 'array',
            'authenticated_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }
}
