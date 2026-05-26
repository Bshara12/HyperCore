<?php

namespace App\Models\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    use HasUlids;

    protected $table = 'notification_templates';

    protected $fillable = [
        'project_id',
        'key',
        'channel',
        'locale',
        'version',
        'subject_template',
        'body_template',
        'variables_schema',
        'defaults',
        'is_active',
    ];

    protected $casts = [
        'version' => 'integer',
        'variables_schema' => 'array',
        'defaults' => 'array',
        'is_active' => 'boolean',
    ];

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'template_id');
    }
}
