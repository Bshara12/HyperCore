<?php

namespace App\Models\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class NotificationServiceClient extends Model
{
    use HasUlids;

    protected $table = 'notification_service_clients';

    protected $fillable = [
        'service_name',
        'token_hash',
        'scopes',
        'allowed_projects',
        'active',
        'last_used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'allowed_projects' => 'array',
        'active' => 'boolean',
        'last_used_at' => 'datetime',
    ];
}
