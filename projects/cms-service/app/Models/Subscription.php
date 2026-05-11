<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_GRACE_PERIOD = 'grace_period';

    protected $fillable = [
        'user_id',
        'project_id',
        'plan_id',
        'payment_id',
        'status',
        'starts_at',
        'ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'auto_renew',
        'metadata'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'auto_renew' => 'boolean',
        'metadata' => 'array'
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(
            SubscriptionPlan::class,
            'plan_id'
        );
    }

    public function usages(): HasMany
    {
        return $this->hasMany(
            SubscriptionUsage::class
        );
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && now()->lt($this->ends_at);
    }

    public function isExpired(): bool
    {
        return now()->gte($this->ends_at);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}