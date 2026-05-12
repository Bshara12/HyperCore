<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
  use HasFactory;

  protected $fillable = [
    'order_id',
    'user_id',
    'project_id',
    'gateway',
    'amount',
    'currency',
    'status',
    'description',
  ];

  protected $casts = [
    'amount' => 'float',
  ];

  // ─── Statuses ───────────────────────────────────────────────────────────

  const STATUS_PENDING = 'pending';

  const STATUS_PAID = 'paid';

  const STATUS_FAILED = 'failed';

  const STATUS_REFUNDED = 'refunded';

  // ─── Relationships ───────────────────────────────────────────────────────

  public function transactions(): HasMany
  {
    return $this->hasMany(Transaction::class);
  }

  // ─── Helpers ─────────────────────────────────────────────────────────────

  public function isPaid(): bool
  {
    return $this->status === self::STATUS_PAID;
  }

  public function isRefunded(): bool
  {
    return in_array($this->status, [
      self::STATUS_REFUNDED,
    ]);
  }

  public function latestTransaction(): ?Transaction
  {
    /** @var Transaction|null */

    return $this->transactions()->latest()->first();
  }
}
