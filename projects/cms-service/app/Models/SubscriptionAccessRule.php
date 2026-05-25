<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionAccessRule extends Model
{
  use HasFactory;
    protected $fillable = [

        'project_id',

        'event_key',

        'requires_subscription',

        'required_feature',

        'is_active',

        'metadata',
    ];

    protected $casts = [

        'requires_subscription' => 'boolean',

        'is_active' => 'boolean',

        'metadata' => 'array',
    ];
}
