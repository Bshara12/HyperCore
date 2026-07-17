<?php

namespace App\Observers;

use App\Models\InstallmentPlan;
use App\Services\MessageBroker\RabbitMQPublisher;

class InstallmentPlanObserver
{
    public function __construct(
        private readonly RabbitMQPublisher $publisher
    ) {}

    /**
     * يُطلَق عند تحديث خطة التقسيط
     *
     * نهتم بحالتين:
     *
     * 1. دفع قسط جديد → isDirty('paid_installments')
     *    المصدر: PayInstallmentAction
     *
     * 2. اكتمال كل الأقساط → isDirty('status') + status = completed
     *    يحدث تلقائياً عندما paid_installments == total_installments
     *
     * 3. تعثّر → isDirty('status') + status = defaulted
     */
    public function updated(InstallmentPlan $plan): void
    {
        // نحمّل Payment لجلب user_id لأن InstallmentPlan لا يحتويه مباشرة
        $plan->loadMissing('payment');

        $userId = (string) $plan->payment?->user_id;

        if (!$userId) {
            return;
        }

        // ─── اكتمال جميع الأقساط ───────────────────────────────────────────
        if ($plan->isDirty('status')
            && $plan->status === InstallmentPlan::STATUS_COMPLETED
        ) {
            $this->publisher->publish('cms.installment.completed', [
                'user_id'             => $userId,
                'payment_id'          => $plan->payment_id,
                'total_installments'  => $plan->total_installments,
                'total_amount'        => $plan->payment?->amount,
                'currency'            => $plan->payment?->currency ?? 'USD',
            ]);

            return;
        }

        // ─── تعثّر في الأقساط ──────────────────────────────────────────────
        if ($plan->isDirty('status')
            && $plan->status === InstallmentPlan::STATUS_DEFAULTED
        ) {
            $this->publisher->publish('cms.installment.defaulted', [
                'user_id'                 => $userId,
                'payment_id'              => $plan->payment_id,
                'paid_installments'       => $plan->paid_installments,
                'total_installments'      => $plan->total_installments,
                'remaining_installments'  => $plan->remainingInstallments(),
                'remaining_amount'        => $plan->remainingAmount(),
                'currency'                => $plan->payment?->currency ?? 'USD',
            ]);

            return;
        }

        // ─── دفع قسط جديد ─────────────────────────────────────────────────
        // نتحقق من تغيّر paid_installments فقط (ليس status)
        if ($plan->isDirty('paid_installments')) {
            $this->publisher->publish('cms.installment.paid', [
                'user_id'                => $userId,
                'payment_id'             => $plan->payment_id,
                'installment_number'     => $plan->paid_installments,
                'total_installments'     => $plan->total_installments,
                'remaining_installments' => $plan->remainingInstallments(),
                'installment_amount'     => $plan->installment_amount,
                'remaining_amount'       => $plan->remainingAmount(),
                'currency'               => $plan->payment?->currency ?? 'USD',
                'next_due_date'          => $plan->next_due_date?->toDateString(),
            ]);
        }
    }
}
