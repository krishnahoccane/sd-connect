<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class ConfigurationHistory extends Model
{
    use HasPublicUuid;

    public $timestamps = false;
    protected $table = 'configuration_history';

    protected $fillable = [
        'uuid',
        'scope',
        'organisation_id',
        'application_id',
        'config_key',
        'previous_value',
        'new_value',
        'changed_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_value' => 'array',
            'new_value' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
