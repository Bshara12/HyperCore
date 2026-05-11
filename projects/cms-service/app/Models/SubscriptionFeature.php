<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionFeature extends Model
{
    protected $fillable = [
        'plan_id',
        'feature_key',
        'feature_type',
        'feature_value'
    ];

    protected $casts = [
        'feature_value' => 'array'
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            SubscriptionPlan::class,
            'plan_id'
        );
    }

    public function isBoolean(): bool
    {
        return $this->feature_type === 'boolean';
    }

    public function isLimit(): bool
    {
        return $this->feature_type === 'limit';
    }
}