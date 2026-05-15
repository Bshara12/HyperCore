<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentAccessMetadata extends Model
{
    protected $fillable = [
        'project_id',
        'content_type',
        'content_id',
        'requires_subscription',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'requires_subscription' => 'boolean',
        'is_active'             => 'boolean',
        'metadata'              => 'array',
    ];

    // ─────────────────────────────────────────────────────────────────

    public function features(): HasMany
    {
        return $this->hasMany(
            ContentAccessFeature::class,
            'content_access_metadata_id'
        );
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Returns an array of allowed feature_key strings.
     * Uses loaded relation when available (no extra query).
     */
    public function allowedFeatureKeys(): array
    {
        return $this->features
            ->pluck('feature_key')
            ->all();
    }

    /**
     * Whether any specific feature key is in the allowed list.
     */
    public function allowsFeature(string $featureKey): bool
    {
        return $this->features
            ->contains('feature_key', $featureKey);
    }

    /**
     * Whether this rule requires a specific feature at all.
     */
    public function requiresFeature(): bool
    {
        return $this->features->isNotEmpty();
    }
}