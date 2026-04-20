<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'project_id',
    'gateway',
    'payment_type',
    'amount',
    'currency',
    'status',
    'description',
  ];

  protected $casts = ['amount' => 'float'];

  // ─── Statuses ─────────────────────────────────────────────────────────────
  const STATUS_PENDING  = 'pending';
  const STATUS_PAID     = 'paid';
  const STATUS_FAILED   = 'failed';
  const STATUS_REFUNDED = 'refunded';

  // ─── Types ────────────────────────────────────────────────────────────────
  const TYPE_FULL        = 'full';
  const TYPE_INSTALLMENT = 'installment';

  // ─── Relationships ────────────────────────────────────────────────────────
  public function project(): BelongsTo
  {
    return $this->belongTo(Project::class);
  }

  public function transactions(): HasMany
  {
    return $this->hasMany(Transaction::class);
  }

  public function installmentPlan(): HasOne
  {
    return $this->hasOne(InstallmentPlan::class);
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────
  public function isPaid(): bool
  {
    return $this->status === self::STATUS_PAID;
  }

  public function isInstallment(): bool
  {
    return $this->payment_type === self::TYPE_INSTALLMENT;
  }

  public function isPaidInFull(): bool
  {
    return $this->transactions()
      ->where('type', Transaction::TYPE_CHARGE)
      ->where('status', Transaction::STATUS_SUCCESS)
      ->sum('amount') >= $this->amount;
  }
  public function latestTransaction(): ?Transaction
  {
    return $this->transactions()->latest()->first();
  }

  public function refundedAmount(): float
  {
    return $this->transactions()
      ->where('type', Transaction::TYPE_REFUND)
      ->where('status', Transaction::STATUS_SUCCESS)
      ->sum('amount');
  }

  public function remainingAmount(): float
  {
    return $this->amount - $this->refundedAmount();
  }
}
