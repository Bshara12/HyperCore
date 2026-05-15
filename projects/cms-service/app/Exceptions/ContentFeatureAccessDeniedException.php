<?php

namespace App\Exceptions;

use App\Domains\Subscription\Enums\SubscriptionErrorCode;

class ContentFeatureAccessDeniedException
    extends SubscriptionException
{
    public function __construct(
        private readonly array $requiredFeatures
    ) {
        parent::__construct(
            'You do not have access to this content. Required one of: '
            . implode(', ', $requiredFeatures)
        );
    }

    public function context(): array
    {
        return [
            'code'              => SubscriptionErrorCode::FEATURE_REQUIRED->value,
            'required_features' => $this->requiredFeatures,
        ];
    }
}