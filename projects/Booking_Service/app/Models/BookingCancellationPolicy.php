<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCancellationPolicy extends Model
{
  protected $fillable = [
    'resource_id',
    'hours_before',
    'refund_percentage',
    'description',
  ];

  protected $casts = [
    'hours_before'      => 'integer',
    'refund_percentage' => 'integer',
  ];

  // ─── Relationships ────────────────────────────────────────────────────────

  public function resource(): BelongsTo
  {
    return $this->belongsTo(Resource::class);
  }

    // ─── Helpers ──────────────────────────────────────────────────────────────

  /**
   * حساب مبلغ الاسترداد بناءً على هذه السياسة
   */
  public function calculateRefund(float $amount): float
  {
    return round($amount * $this->refund_percentage / 100, 2);
  }
}
