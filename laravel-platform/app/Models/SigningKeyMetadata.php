<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class SigningKeyMetadata extends Model
{
    use HasPublicUuid;

    protected $table = 'signing_key_metadata';

    protected $fillable = [
        'uuid',
        'kid',
        'algorithm',
        'public_key_pem',
        'encrypted_private_key_pem',
        'status',
        'activated_at',
        'expires_at',
        'retired_at',
    ];

    protected $hidden = [
        'encrypted_private_key_pem',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_private_key_pem' => 'encrypted',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'retired_at' => 'datetime',
        ];
    }
}
