<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $resource_id
 * @property int $user_id
 * @property int $project_id
 * @property string $status
 * @property Carbon $start_at
 * @property Carbon $end_at
 * @property float $amount
 */
class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'resource_id',
        'user_id',
        'project_id',
        'payment_id',
        'start_at',
        'end_at',
        'status',
        'amount',
        'currency',
        'notes',
        'cancellation_reason',
        'refund_amount',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'amount' => 'float',
        'refund_amount' => 'float',
    ];

    // ─── Statuses ─────────────────────────────────────────────────────────────

    const STATUS_PENDING = 'pending';

    const STATUS_CONFIRMED = 'confirmed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_COMPLETED = 'completed';

    const STATUS_NO_SHOW = 'no_show';

    // ─── Relationships ────────────────────────────────────────────────────────

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
        ]);
    }

    public function isReschedulable(): bool
    {
        return $this->status === self::STATUS_CONFIRMED
          && $this->start_at->isFuture();
    }

    /**
     * عدد الساعات المتبقية حتى الحجز
     */
    public function hoursUntilBooking(): float
    {
        return now()->diffInHours($this->start_at, false);
    }

    /**
     * مدة الحجز بالدقائق
     */
    public function durationInMinutes(): float
    {
        return $this->start_at->diffInMinutes($this->end_at);
    }
}
