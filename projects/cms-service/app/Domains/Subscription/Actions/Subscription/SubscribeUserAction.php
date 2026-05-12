<?php

namespace App\Domains\Subscription\Actions\Subscription;

use Exception;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Services\PaymentService;
use App\Domains\Subscription\DTOs\Subscription\SubscribeUserDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;

class SubscribeUserAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $repository,
        private PaymentService $paymentService
    ) {}

    public function execute(
        SubscribeUserDTO $dto
    ): Subscription {

        $plan = SubscriptionPlan::query()
            ->findOrFail($dto->planId);

        $this->ensurePlanIsActive($plan);

        $this->ensureUserHasNoActiveSubscription(
            $dto->userId,
            $plan->project_id
        );

        $paymentResult = $this->processPayment(
            $dto,
            $plan
        );

        return $this->repository->create(
            dto: $dto,
            plan: $plan,
            paymentId: $paymentResult['payment_id']
        );
    }

    private function processPayment(
        SubscribeUserDTO $dto,
        SubscriptionPlan $plan
    ): array {

        // FREE PLAN
        if ((float)$plan->price <= 0) {

            return [
                'success' => true,
                'payment_id' => null
            ];
        }

        $paymentDTO = PaymentDTO::fromArray([

            'user_id' => $dto->userId,

            'user_name' => $dto->userName,

            'project_id' => $plan->project_id,

            'amount' => $plan->price,

            'currency' => $plan->currency,

            'gateway' => $dto->gateway,

            'payment_type' => $dto->paymentType,

            'description' => sprintf(
                'Subscription payment for %s',
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

        return $result;
    }

    private function ensurePlanIsActive(
        SubscriptionPlan $plan
    ): void {

        if (!$plan->is_active) {

            throw new Exception(
                'Subscription plan is inactive.'
            );
        }
    }

    private function ensureUserHasNoActiveSubscription(
        int $userId,
        ?int $projectId
    ): void {

        $exists = $this->repository
            ->hasActiveSubscription(
                $userId,
                $projectId
            );

        if ($exists) {

            throw new Exception(
                'User already has an active subscription.'
            );
        }
    }
}