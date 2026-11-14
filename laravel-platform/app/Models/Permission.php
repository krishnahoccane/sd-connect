<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'scope',
        'slug',
        'name',
        'module',
        'description',
    ];
}
