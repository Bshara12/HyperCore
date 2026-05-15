<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAccessFeature extends Model
{
    protected $fillable = [
        'content_access_metadata_id',
        'feature_key',
    ];

    public function contentAccess(): BelongsTo
    {
        return $this->belongsTo(
            ContentAccessMetadata::class,
            'content_access_metadata_id'
        );
    }
}