<?php

namespace App\Domains\Subscription\Actions\Rule;

use App\Models\SubscriptionFeatureRule;
use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionFeatureRuleRepositoryInterface;

class CreateFeatureRuleAction
{
    public function __construct(
        private SubscriptionFeatureRuleRepositoryInterface $repository
    ) {}

    public function execute(
        CreateFeatureRuleDTO $dto
    ): SubscriptionFeatureRule {

        return $this->repository
            ->create($dto);
    }
}