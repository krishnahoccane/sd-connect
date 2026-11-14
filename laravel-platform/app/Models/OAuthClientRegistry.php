<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthClientRegistry extends Model
{
    use HasPublicUuid;

    protected $table = 'oauth_client_registry';

    protected $fillable = [
        'uuid',
        'organisation_id',
        'application_id',
        'passport_client_id',
        'client_type',
        'pkce_required',
        'is_active',
        'secret_rotated_at',
    ];

    protected function casts(): array
    {
        return [
            'pkce_required' => 'boolean',
            'is_active' => 'boolean',
            'secret_rotated_at' => 'datetime',
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
}
