<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentAccessMetadata extends Model
{
  protected $fillable = [

    'project_id',

    'content_type',

    'content_id',

    'requires_subscription',

    'required_feature',

    'metadata',

    'is_active',
  ];

  protected $casts = [

    'requires_subscription' => 'boolean',
    'is_active' => 'boolean',
    'metadata' => 'array'
  ];
}
