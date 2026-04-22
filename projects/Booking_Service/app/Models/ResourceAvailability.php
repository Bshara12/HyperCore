<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAvailability extends Model
{
  protected $fillable = [
    'resource_id',
    'day_of_week',
    'start_time',
    'end_time',
    'slot_duration',
    'is_active',
  ];

  protected $casts = [
    'day_of_week'    => 'integer',
    'slot_duration'  => 'integer',
    'is_active'      => 'boolean',
  ];

  // ─── Days ─────────────────────────────────────────────────────────────────

  const DAYS = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday',
  ];

  // ─── Relationships ────────────────────────────────────────────────────────

  public function resource(): BelongsTo
  {
    return $this->belongsTo(Resource::class);
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────

  public function dayName(): string
  {
    return self::DAYS[$this->day_of_week] ?? 'Unknown';
  }

  /**
   * عدد الـ slots في هذا اليوم
   * مثال: 9:00 → 17:00 بـ slot_duration = 60 → 8 slots
   */
  public function slotsCount(): int
  {
    $start   = strtotime($this->start_time);
    $end     = strtotime($this->end_time);
    $minutes = ($end - $start) / 60;

    return (int) floor($minutes / $this->slot_duration);
  }
}
