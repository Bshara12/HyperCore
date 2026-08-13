<?php

namespace App\Exceptions;

use App\Domains\Subscription\Enums\SubscriptionErrorCode;

class SubscriptionAccessDeniedException extends SubscriptionException
{
    public function __construct(
        private readonly int $subscriptionId
    ) {

        parent::__construct(
            sprintf(
                'You are not allowed to access subscription [%d].',
                $subscriptionId
            )
        );
    }

    public function context(): array
    {
        return [

            'code' => SubscriptionErrorCode::SUBSCRIPTION_ACCESS_DENIED
                ->value,

            'subscription_id' => $this->subscriptionId,
        ];
    }
}