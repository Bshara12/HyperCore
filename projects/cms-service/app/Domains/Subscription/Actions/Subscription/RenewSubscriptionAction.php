<?php

namespace App\Domains\Subscription\Actions\Subscription;

use Exception;
use App\Models\Payment;
use App\Models\Subscription;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Services\PaymentService;
use App\Domains\Subscription\DTOs\Subscription\RenewSubscriptionDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;

class RenewSubscriptionAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $repository,
        private PaymentService $paymentService
    ) {}

    public function execute(
        RenewSubscriptionDTO $dto
    ): Subscription {

        $subscription = $dto->subscription;

        $plan = $subscription->plan;

        $this->processPayment(
            $dto,
            $subscription
        );

        $newStartDate = $this->resolveNewStartDate(
            $subscription
        );

        $newEndDate = (clone $newStartDate)
            ->addDays($plan->duration_days);

        return $this->repository->renew(
            $subscription,
            [

                'status' => Subscription::STATUS_ACTIVE,

                'starts_at' => $subscription->starts_at,

                'ends_at' => $newEndDate,

                'current_period_start' => $newStartDate,

                'current_period_end' => $newEndDate,

                'auto_renew' => $dto->autoRenew
                    ?? $subscription->auto_renew,

                'metadata' => array_merge(
                    $subscription->metadata ?? [],
                    $dto->metadata ?? []
                )
            ]
        );
    }

    private function processPayment(
        RenewSubscriptionDTO $dto,
        Subscription $subscription
    ): void {

        $plan = $subscription->plan;

        // FREE PLAN
        if ((float)$plan->price <= 0) {
            return;
        }

        $paymentDTO = PaymentDTO::fromArray([

            'user_id' => $dto->userId,

            'user_name' => $dto->userName,

            'project_id' => $subscription->project_id,

            'amount' => $plan->price,

            'currency' => $plan->currency,

            'gateway' => $dto->gateway,

            'payment_type' => $dto->paymentType,

            'description' => sprintf(
                'Renew subscription for %s',
                $plan->name
            )
        ]);

        $result = $this->paymentService
            ->processPayment($paymentDTO);

        if (!$result['success']) {

            throw new Exception(
                'Payment failed.'
            );
        }

        if (
            $result['status'] !== Payment::STATUS_PAID
            &&
            $result['status'] !== Payment::STATUS_PENDING
        ) {

            throw new Exception(
                'Payment was not completed.'
            );
        }
    }

    private function resolveNewStartDate(
        Subscription $subscription
    ) {

        if (
            $subscription->ends_at->isFuture()
        ) {

            return $subscription->ends_at;
        }

        return now();
    }
}