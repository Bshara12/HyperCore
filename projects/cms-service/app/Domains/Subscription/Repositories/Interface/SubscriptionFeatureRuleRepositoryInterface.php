<?php

namespace App\Domains\Subscription\Repositories\Interface;

use App\Models\SubscriptionFeatureRule;
use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;

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
