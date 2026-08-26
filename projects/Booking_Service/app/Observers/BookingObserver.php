<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\MessageBroker\RabbitMQPublisher;

class BookingObserver
{
    public function __construct(
        private readonly RabbitMQPublisher $publisher
    ) {}

    /**
     * يُطلَق عند إنشاء حجز جديد
     *
     * المصدر: CreateBookingRecordAction::execute()
     *   → $this->bookingRepository->create([...])
     *   → Booking::create() ← هنا يُطلَق هذا الـ hook
     *
     * الحالة دائماً 'pending' عند الإنشاء
     */
    public function created(Booking $booking): void
    {
        $this->publisher->publish('booking.booking.created', [
            'user_id' => (string) $booking->user_id,
            'booking_id' => $booking->id,
            'resource_id' => $booking->resource_id,
            'start_at' => $booking->start_at->toIso8601String(),
            'end_at' => $booking->end_at->toIso8601String(),
            'amount' => $booking->amount,
            'currency' => $booking->currency,
            'status' => $booking->status,
        ]);
    }

    /**
     * يُطلَق عند تحديث الحجز
     *
     * نفرّق بين حالتين باستخدام isDirty():
     *
     * ─── حالة الإلغاء ────────────────────────────────────────────────────
     * المصدر: UpdateBookingStatusAction::execute()
     *   → $booking->update(['status' => 'cancelled', ...])
     *   → isDirty('status') = true + status == 'cancelled'
     *
     * ─── حالة إعادة الجدولة ──────────────────────────────────────────────
     * المصدر: UpdateBookingTimeAction::execute()
     *   → $booking->update(['start_at' => $start, 'end_at' => $end])
     *   → isDirty('start_at') || isDirty('end_at') = true
     *   → status لم يتغير → ليس إلغاءً
     */
    public function updated(Booking $booking): void
    {
        // ─── إلغاء الحجز ──────────────────────────────────────────────────
        if ($booking->isDirty('status') && $booking->status === Booking::STATUS_CANCELLED) {
            $this->publisher->publish('booking.booking.cancelled', [
                'user_id' => (string) $booking->user_id,
                'booking_id' => $booking->id,
                'resource_id' => $booking->resource_id,
                'start_at' => $booking->start_at->toIso8601String(),
                'end_at' => $booking->end_at->toIso8601String(),
                'cancellation_reason' => $booking->cancellation_reason,
                'refund_amount' => $booking->refund_amount,
                'currency' => $booking->currency,
            ]);

            return; // نخرج فوراً لأن الحدثين متضادان
        }

        // ─── إعادة الجدولة ────────────────────────────────────────────────
        // نتحقق أن start_at أو end_at تغيّرا بغض النظر عن الـ status
        if ($booking->isDirty('start_at') || $booking->isDirty('end_at')) {
            $this->publisher->publish('booking.booking.rescheduled', [
                'user_id' => (string) $booking->user_id,
                'booking_id' => $booking->id,
                'resource_id' => $booking->resource_id,
                // getOriginal() يعيد القيمة قبل التحديث (مفيد لإظهار الموعد القديم في الإشعار)
                'old_start_at' => $booking->getOriginal('start_at'),
                'old_end_at' => $booking->getOriginal('end_at'),
                'new_start_at' => $booking->start_at->toIso8601String(),
                'new_end_at' => $booking->end_at->toIso8601String(),
                'amount' => $booking->amount,
                'currency' => $booking->currency,
            ]);
        }
    }
}
