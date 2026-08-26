<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @method static \Database\Factories\TransactionFactory factory($count = null, $state = [])
 */
class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
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
        'amount' => 'float',
        'gateway_response' => 'array',
        'processed_at' => 'datetime',
    ];

    const TYPE_CHARGE = 'charge';
    const TYPE_REFUND = 'refund';

    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_PENDING = 'pending';

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

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