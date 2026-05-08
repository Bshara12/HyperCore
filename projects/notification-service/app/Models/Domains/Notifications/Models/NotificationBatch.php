<?php

namespace App\Models\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationBatch extends Model
{
    use HasUlids, MassPrunable;

    protected $table = 'notification_batches';

    protected $fillable = [
        'project_id',
        'created_by_type',
        'created_by_id',
        'correlation_id',
        'causation_id',
        'request_id',
        'actor_snapshot',
        'source_snapshot',
        'audit_meta',
        'source_service',
        'source_event_type',
        'audience_type',
        'audience_query',
        'payload',
        'status',
        'dedupe_key',
        'total_targets',
        'processed_targets',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'actor_snapshot' => 'array',
        'source_snapshot' => 'array',
        'audit_meta' => 'array',
        'audience_query' => 'array',
        'payload' => 'array',
        'total_targets' => 'integer',
        'processed_targets' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::query()
            ->whereIn('status', ['completed', 'failed'])
            ->where('created_at', '<', now()->subDays(30));
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }
}
