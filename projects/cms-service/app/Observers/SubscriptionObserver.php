<?php

namespace App\Observers;

use App\Models\Subscription;
use App\Services\MessageBroker\RabbitMQPublisher;

class SubscriptionObserver
{
    public function __construct(
        private readonly RabbitMQPublisher $publisher
    ) {}

    /**
     * يُطلَق من SubscribeUserAction → repository->create()
     *
     * الحالة عند الإنشاء:
     *   - active  → خطة مجانية أو دفع فوري ناجح
     *   - pending → دفع بالتقسيط لم يكتمل بعد
     */
    public function created(Subscription $subscription): void
    {
        // نحمّل الـ plan لنرسل اسمه وتفاصيله في الإشعار
        $subscription->loadMissing('plan');

        $this->publisher->publish('cms.subscription.created', [
            'user_id'         => (string) $subscription->user_id,
            'subscription_id' => $subscription->id,
            'plan_id'         => $subscription->plan_id,
            'plan_name'       => $subscription->plan?->name,
            'plan_price'      => $subscription->plan?->price,
            'currency'        => $subscription->plan?->currency ?? 'USD',
            'status'          => $subscription->status,
            'starts_at'       => $subscription->starts_at?->toIso8601String(),
            'ends_at'         => $subscription->ends_at?->toIso8601String(),
            'auto_renew'      => $subscription->auto_renew,
        ]);
    }

    /**
     * يُطلَق عند أي تحديث على الـ Subscription
     * نُفرِّق بين 4 حالات:
     *
     * 1. إلغاء  → CancelSubscriptionAction → isDirty('status') + cancelled
     *
     * 2. تجديد  → RenewSubscriptionAction  → isDirty('ends_at') + status = active
     *           → AutoRenewSubscriptions   → نفس الشرط (تجديد تلقائي)
     *
     * 3. فترة السماح → AutoRenewSubscriptions فشل الدفع
     *               → isDirty('status') + grace_period
     *
     * 4. انتهاء → Scheduler أو يدوياً
     *           → isDirty('status') + expired
     */
    public function updated(Subscription $subscription): void
    {
        $subscription->loadMissing('plan');

        // ─── إلغاء الاشتراك ────────────────────────────────────────────────
        if ($subscription->isDirty('status')
            && $subscription->status === Subscription::STATUS_CANCELLED
        ) {
            $this->publisher->publish('cms.subscription.cancelled', [
                'user_id'         => (string) $subscription->user_id,
                'subscription_id' => $subscription->id,
                'plan_name'       => $subscription->plan?->name,
                'cancelled_at'    => $subscription->cancelled_at?->toIso8601String(),
                'ends_at'         => $subscription->ends_at?->toIso8601String(),
                'cancel_reason'   => $subscription->metadata['cancel_reason'] ?? null,
            ]);

            return;
        }

        // ─── فترة السماح (فشل التجديد التلقائي) ──────────────────────────
        if ($subscription->isDirty('status')
            && $subscription->status === Subscription::STATUS_GRACE_PERIOD
        ) {
            $this->publisher->publish('cms.subscription.grace_period', [
                'user_id'         => (string) $subscription->user_id,
                'subscription_id' => $subscription->id,
                'plan_name'       => $subscription->plan?->name,
                'ends_at'         => $subscription->ends_at?->toIso8601String(),
            ]);

            return;
        }

        // ─── انتهاء الاشتراك ───────────────────────────────────────────────
        if ($subscription->isDirty('status')
            && $subscription->status === Subscription::STATUS_EXPIRED
        ) {
            $this->publisher->publish('cms.subscription.expired', [
                'user_id'         => (string) $subscription->user_id,
                'subscription_id' => $subscription->id,
                'plan_name'       => $subscription->plan?->name,
                'ended_at'        => $subscription->ends_at?->toIso8601String(),
            ]);

            return;
        }

        // ─── التجديد (يدوي أو تلقائي) ─────────────────────────────────────
        // الشرط: ends_at تغيّر + الحالة active
        // يشمل: RenewSubscriptionAction + AutoRenewSubscriptionsAction
        // التمييز بين اليدوي والتلقائي عبر getOriginal('status'):
        //   - getOriginal('status') == grace_period → تجديد تلقائي بعد فترة السماح
        //   - getOriginal('status') == active       → تجديد عادي
        if ($subscription->isDirty('ends_at')
            && $subscription->status === Subscription::STATUS_ACTIVE
        ) {
            $wasInGracePeriod = $subscription->getOriginal('status')
                === Subscription::STATUS_GRACE_PERIOD;

            $this->publisher->publish('cms.subscription.renewed', [
                'user_id'          => (string) $subscription->user_id,
                'subscription_id'  => $subscription->id,
                'plan_name'        => $subscription->plan?->name,
                'plan_price'       => $subscription->plan?->price,
                'currency'         => $subscription->plan?->currency ?? 'USD',
                'new_ends_at'      => $subscription->ends_at?->toIso8601String(),
                'is_auto_renewed'  => $wasInGracePeriod,
            ]);
        }
    }
}
