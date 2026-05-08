<?php

namespace App\Models\Domains\Notifications\Models;

use App\Domains\Notifications\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    use HasUlids;

    protected $table = 'notification_deliveries';

    protected $fillable = [
        'notification_id',
        'channel',
        'provider',
        'status',
        'attempts',
        'max_attempts',
        'last_attempt_at',
        'next_retry_at',
        'provider_message_id',
        'payload_snapshot',
        'error_code',
        'error_message',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'status' => DeliveryStatus::class,
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'payload_snapshot' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }
}
