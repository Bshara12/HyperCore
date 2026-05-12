<?php

namespace App\Domains\Subscription\Actions\Usage;

use Carbon\Carbon;
use App\Models\SubscriptionUsage;
use App\Models\SubscriptionFeatureRule;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;

class ResetSubscriptionUsageAction
{
    public function __construct(

        private SubscriptionRepositoryInterface
        $repository
    ) {}

    public function execute(): void
    {
        SubscriptionUsage::query()

            ->whereNotNull('reset_at')

            ->where('reset_at', '<=', now())

            ->chunkById(100, function ($usages) {

                foreach ($usages as $usage) {

                    $this->resetUsage(
                        $usage
                    );
                }
            });
    }

    // ─────────────────────────────────────

    private function resetUsage(
        SubscriptionUsage $usage
    ): void {

        $subscription = $usage
            ->subscription;

        if (!$subscription) {
            return;
        }

        $rule = SubscriptionFeatureRule::query()

            ->where(
                'project_id',
                $subscription->project_id
            )

            ->where(
                'feature_key',
                $usage->feature_key
            )

            ->where('is_active', true)

            ->first();

        if (!$rule) {
            return;
        }

        $nextResetAt = $this->resolveNextResetDate(
            $rule->reset_type
        );

        $this->repository->resetUsage(

            subscriptionId:
                $subscription->id,

            featureKey:
                $usage->feature_key,

            nextResetAt:
                $nextResetAt
        );
    }

    // ─────────────────────────────────────

    private function resolveNextResetDate(
        string $resetType
    ): ?string {

        return match ($resetType) {

            SubscriptionFeatureRule::RESET_DAILY =>
                now()->addDay(),

            SubscriptionFeatureRule::RESET_MONTHLY =>
                now()->addMonth(),

            SubscriptionFeatureRule::RESET_YEARLY =>
                now()->addYear(),

            default => null
        };
    }
}