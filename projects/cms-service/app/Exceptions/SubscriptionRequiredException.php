<?php

namespace App\Exceptions;

class SubscriptionRequiredException
extends SubscriptionException
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

            'code' => 'SUBSCRIPTION_REQUIRED',

            'required_features' => $this->requiredFeatures,
        ];
    }
}