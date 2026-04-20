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
    'type',
    'payment_method',
    'gateway_transaction_id',
    'gateway_response',
    'from_wallet_id',
    'to_wallet_id',
    'installment_number',
    'amount',
    'currency',
    'status',
    'processed_at',
  ];

  protected $casts = [
    'amount'           => 'float',
    'gateway_response' => 'array',
    'processed_at'     => 'datetime',
  ];

  // ─── Types ────────────────────────────────────────────────────────────────
  const TYPE_CHARGE = 'charge';
  const TYPE_REFUND = 'refund';

  // ─── Payment Methods ──────────────────────────────────────────────────────
  const METHOD_GATEWAY = 'gateway';
  const METHOD_WALLET  = 'wallet';

  // ─── Statuses ─────────────────────────────────────────────────────────────
  const STATUS_PENDING = 'pending';
  const STATUS_SUCCESS = 'success';
  const STATUS_FAILED  = 'failed';

  // ─── Relationships ────────────────────────────────────────────────────────
  public function payment(): BelongsTo
  {
    return $this->belongsTo(Payment::class);
  }

  public function fromWallet(): BelongsTo
  {
    return $this->belongsTo(Wallet::class, 'from_wallet_id');
  }

  public function toWallet(): BelongsTo
  {
    return $this->belongsTo(Wallet::class, 'to_wallet_id');
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────
  public function isSuccess(): bool
  {
    return $this->status === self::STATUS_SUCCESS;
  }
  public function isGateway(): bool
  {
    return $this->payment_method === self::METHOD_GATEWAY;
  }
  public function isWallet(): bool
  {
    return $this->payment_method === self::METHOD_WALLET;
  }
  public function isDownPayment(): bool
  {
    return $this->installment_number === 0;
  }
}
