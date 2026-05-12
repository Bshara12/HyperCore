<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionFeatureRule extends Model
{
    protected $fillable = [

        'project_id',

        'event_key',

        'feature_key',

        'action',

        'reset_type',

        'is_active',

        'metadata'
    ];

    protected $casts = [

        'is_active' => 'boolean',

        'metadata' => 'array'
    ];

    // ─────────────────────────────────────

    const ACTION_CHECK = 'check';

    const ACTION_INCREMENT = 'increment';

    const ACTION_BOTH = 'both';

    // ─────────────────────────────────────

    const RESET_NEVER = 'never';

    const RESET_DAILY = 'daily';

    const RESET_MONTHLY = 'monthly';

    const RESET_YEARLY = 'yearly';
}