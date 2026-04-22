<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
  use SoftDeletes;

  protected $fillable = [
    'data_entry_id',
    'project_id',
    'name',
    'type',
    'capacity',
    'status',
    'payment_type',
    'price',
    'settings',
  ];

  protected $casts = [
    'capacity'    => 'integer',
    'price'       => 'float',
    'settings'    => 'array',
  ];

  // ─── Statuses ─────────────────────────────────────────────────────────────

  const STATUS_ACTIVE   = 'active';
  const STATUS_INACTIVE = 'inactive';

  // ─── Payment Types ────────────────────────────────────────────────────────

  const PAYMENT_FREE = 'free';
  const PAYMENT_PAID = 'paid';

  // ─── Relationships ────────────────────────────────────────────────────────

  public function availabilities(): HasMany
  {
    return $this->hasMany(ResourceAvailability::class);
  }

  public function activeAvailabilities(): HasMany
  {
    return $this->hasMany(ResourceAvailability::class)
      ->where('is_active', true);
  }

  public function cancellationPolicies(): HasMany
  {
    return $this->hasMany(BookingCancellationPolicy::class)
      ->orderByDesc('hours_before');
  }

  public function bookings(): HasMany
  {
    return $this->hasMany(Booking::class);
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────

  public function isActive(): bool
  {
    return $this->status === self::STATUS_ACTIVE;
  }

  public function isBookable(): bool
  {
    return $this->isActive();
  }

  public function isFree(): bool
  {
    return $this->payment_type === self::PAYMENT_FREE;
  }

  public function isPaid(): bool
  {
    return $this->payment_type === self::PAYMENT_PAID;
  }

  public function availabilityForDay(int $dayOfWeek): ?ResourceAvailability
  {
    return $this->activeAvailabilities()
      ->where('day_of_week', $dayOfWeek)
      ->first();
  }
}
