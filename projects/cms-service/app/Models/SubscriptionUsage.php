<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUsage extends Model
{
  protected $fillable = [
    'subscription_id',
    'feature_key',
    'used_value',
    'reset_at'
  ];

  protected $casts = [
    'reset_at' => 'datetime'
  ];

  public function subscription(): BelongsTo
  {
    return $this->belongsTo(
      Subscription::class
    );
  }

  public function incrementUsage(
    int $amount = 1
  ): void {

    $this->increment(
      'used_value',
      $amount
    );
  }
}
