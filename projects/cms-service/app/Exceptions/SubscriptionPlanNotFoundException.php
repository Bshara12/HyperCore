<?php

namespace App\Exceptions;

use App\Domains\Subscription\Enums\SubscriptionErrorCode;

class SubscriptionPlanNotFoundException extends SubscriptionException
{
    public function __construct(
        private readonly int $planId
    ) {

        parent::__construct(
            sprintf(
                'Subscription plan [%d] not found.',
                $planId
            )
        );
    }

    public function context(): array
    {
        return [

            'code' => SubscriptionErrorCode::PLAN_NOT_FOUND
                ->value,

            'plan_id' => $this->planId,
        ];
    }
}