<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganisationBranding extends Model
{
    use HasPublicUuid;

    protected $table = 'organisation_branding';

    protected $fillable = [
        'uuid',
        'organisation_id',
        'published_version_id',
        'draft_version_id',
    ];

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(BrandingVersion::class, 'published_version_id');
    }

    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(BrandingVersion::class, 'draft_version_id');
    }
}
