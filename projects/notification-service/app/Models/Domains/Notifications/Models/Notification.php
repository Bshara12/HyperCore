<?php

namespace App\Models\Domains\Notifications\Models;

use App\Domains\Notifications\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notification extends Model
{
    use HasUlids, MassPrunable;

    protected $table = 'notifications';

    protected $fillable = [
        'project_id',
        'recipient_type',
        'recipient_id',
        'source_type',
        'source_service',
        'source_id',
        'created_by_type',
        'created_by_id',
        'correlation_id',
        'causation_id',
        'request_id',
        'actor_snapshot',
        'source_snapshot',
        'audit_meta',
        'template_id',
        'topic_key',
        'title',
        'body',
        'data',
        'metadata',
        'priority',
        'status',
        'scheduled_at',
        'queued_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'dedupe_key',
        'batch_id',
    ];

    protected $casts = [
        'actor_snapshot' => 'array',
        'source_snapshot' => 'array',
        'audit_meta' => 'array',
        'data' => 'array',
        'metadata' => 'array',
        'priority' => 'integer',
        'status' => NotificationStatus::class,
        'scheduled_at' => 'datetime',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::query()
            ->whereIn('status', [
                NotificationStatus::Read->value,
                NotificationStatus::Delivered->value,
                NotificationStatus::Failed->value,
                NotificationStatus::Cancelled->value,
            ])
            ->where('created_at', '<', now()->subDays(90));
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }
}
