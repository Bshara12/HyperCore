<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CircuitBreaker extends Model
{
  protected $fillable = [
    'service_name',
    'state',
    'failure_count',
    'failure_threshold',
    'opened_at',
    'next_attempt_at',
  ];
}
