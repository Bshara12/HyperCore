<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstallmentPlan extends Model
{
  protected $fillable = [
    'payment_id',
    'down_payment',
    'installment_amount',
    'total_installments',
    'paid_installments',
    'interval_days',
    'next_due_date',
    'status',
  ];

  protected $casts = [
    'down_payment'       => 'float',
    'installment_amount' => 'float',
    'next_due_date'      => 'date',
  ];

  // ─── Statuses ─────────────────────────────────────────────────────────────
  const STATUS_ACTIVE    = 'active';
  const STATUS_COMPLETED = 'completed';
  const STATUS_DEFAULTED = 'defaulted';

  // ─── Relationships ────────────────────────────────────────────────────────
  public function payment(): BelongsTo
  {
    return $this->belongsTo(Payment::class);
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────
  public function isCompleted(): bool
  {
    return $this->paid_installments >= $this->total_installments;
  }

  public function remainingInstallments(): int
  {
    return $this->total_installments - $this->paid_installments;
  }

  public function remainingAmount(): float
  {
    return $this->remainingInstallments() * $this->installment_amount;
  }

  public function nextInstallmentNumber(): int
  {
    return $this->paid_installments + 1;
  }
}