<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
  use HasFactory;

  protected $fillable = [
    'payment_id',
    'gateway_transaction_id',
    'type',
    'amount',
    'currency',
    'status',
    'gateway_response',
    'processed_at',
  ];

  protected $casts = [
    'amount'           => 'float',
    'gateway_response' => 'array',
    'processed_at'     => 'datetime',
  ];

  // ─── Types ────────────────────────────────────────────────────────────────

  const TYPE_CHARGE  = 'charge';
  const TYPE_REFUND  = 'refund';

  // ─── Statuses ─────────────────────────────────────────────────────────────

  const STATUS_SUCCESS = 'success';
  const STATUS_FAILED  = 'failed';
  const STATUS_PENDING = 'pending';

  // ─── Relationships ────────────────────────────────────────────────────────

  public function payment(): BelongsTo
  {
    return $this->belongsTo(Payment::class);
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────

  public function isSuccess(): bool
  {
    return $this->status === self::STATUS_SUCCESS;
  }

  public function isCharge(): bool
  {
    return $this->type === self::TYPE_CHARGE;
  }

  public function isRefund(): bool
  {
    return $this->type === self::TYPE_REFUND;
  }
}
