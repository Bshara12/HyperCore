<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property float $price
 * @property string $currency
 * @property int $duration_days
 *
 * @property-read Collection<int, SubscriptionFeature> $features
 */
class SubscriptionPlan extends Model
{
  use HasFactory;
  protected $fillable = [
    'project_id',
    'name',
    'slug',
    'description',
    'price',
    'currency',
    'duration_days',
    'is_active',
    'metadata',
  ];

  protected $casts = [
    'price' => 'decimal:2',
    'is_active' => 'boolean',
    'metadata' => 'array',
  ];

  public function features(): HasMany
  {
    return $this->hasMany(
      SubscriptionFeature::class,
      'plan_id'
    );
  }

  public function subscriptions(): HasMany
  {
    return $this->hasMany(
      Subscription::class,
      'plan_id'
    );
  }

  public function isFree(): bool
  {
    return $this->price <= 0;
  }
}
