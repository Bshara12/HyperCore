<?php

namespace App\Domains\Subscription\Repositories\Interface;

use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;
use App\Models\SubscriptionFeatureRule;

interface SubscriptionFeatureRuleRepositoryInterface
{
    public function create(
        CreateFeatureRuleDTO $dto
    ): SubscriptionFeatureRule;

    public function findActiveRulesByEvent(
        ?int $projectId,
        string $eventKey
    );
}
