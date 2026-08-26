<?php

namespace App\Exceptions;

use App\Domains\Subscription\Enums\SubscriptionErrorCode;

class SubscriptionRequiredException extends SubscriptionException
{
    public function __construct(
        private readonly array $requiredFeatures = []
    ) {

        parent::__construct(
            'Subscription required.'
        );
    }

    public function context(): array
    {
        return [

            'code' => SubscriptionErrorCode::SUBSCRIPTION_REQUIRED
                ->value,

            'required_features' => $this->requiredFeatures,
        ];
    }
}
