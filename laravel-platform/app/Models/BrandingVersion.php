<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class BrandingVersion extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'uuid',
        'organisation_id',
        'version_number',
        'display_name',
        'logo_path',
        'background_image_path',
        'primary_color',
        'secondary_color',
        'footer_text',
        'support_email',
        'terms_url',
        'privacy_url',
        'is_published',
        'created_by_user_id',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
