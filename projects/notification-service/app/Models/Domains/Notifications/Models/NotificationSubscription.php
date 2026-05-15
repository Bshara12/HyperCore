<?php

namespace App\Models\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class NotificationSubscription extends Model
{
    use HasUlids;

    protected $table = 'notification_subscriptions';

    protected $fillable = [
        'project_id',
        'subscriber_type',
        'subscriber_id',
        'topic_key',
        'channel_mask',
        'filters',
        'active',
    ];

    protected $casts = [
        'channel_mask' => 'array',
        'filters' => 'array',
        'active' => 'boolean',
    ];
}
