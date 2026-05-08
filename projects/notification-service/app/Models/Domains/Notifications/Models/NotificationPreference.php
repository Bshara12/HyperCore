<?php

namespace App\Models\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class NotificationPreference extends Model
{
    use HasUlids;

    protected $table = 'notification_preferences';

    protected $fillable = [
        'project_id',
        'recipient_type',
        'recipient_id',
        'topic_key',
        'channel',
        'enabled',
        'mute_until',
        'quiet_hours',
        'delivery_mode',
        'locale',
        'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'mute_until' => 'datetime',
        'quiet_hours' => 'array',
        'metadata' => 'array',
    ];
}
