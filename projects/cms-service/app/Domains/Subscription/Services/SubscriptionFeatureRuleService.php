<?php

namespace App\Domains\Subscription\Services;

use App\Models\SubscriptionFeatureRule;
use App\Domains\Subscription\Actions\Rule\CreateFeatureRuleAction;
use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;

class SubscriptionFeatureRuleService
{
    public function __construct(
        private CreateFeatureRuleAction $createAction
    ) {}

    public function create(
        CreateFeatureRuleDTO $dto
    ): SubscriptionFeatureRule {

        return $this->createAction
            ->execute($dto);
    }
}