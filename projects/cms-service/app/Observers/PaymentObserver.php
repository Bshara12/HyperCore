<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\MessageBroker\RabbitMQPublisher;

class PaymentObserver
{
    public function __construct(
        private readonly RabbitMQPublisher $publisher
    ) {}

    /**
     * يُطلَق عند تحديث حالة الدفع
     *
     * الحالات التي نهتم بها:
     *   pending → paid      → دفع ناجح
     *   pending → failed    → دفع فاشل
     *   paid    → refunded  → استرداد
     */
    public function updated(Payment $payment): void
    {
        if (!$payment->isDirty('status')) {
            return;
        }

        $newStatus = $payment->status;

        if (!in_array($newStatus, [
            Payment::STATUS_PAID,
            Payment::STATUS_FAILED,
            Payment::STATUS_REFUNDED,
        ])) {
            return;
        }

        $this->publisher->publish("cms.payment.{$newStatus}", [
            'user_id'       => (string) $payment->user_id,
            'payment_id'    => $payment->id,
            'amount'        => $payment->amount,
            'currency'      => $payment->currency,
            'gateway'       => $payment->gateway,
            'payment_type'  => $payment->payment_type, // full | installment
            'old_status'    => $payment->getOriginal('status'),
            'new_status'    => $newStatus,
        ]);
    }
}
