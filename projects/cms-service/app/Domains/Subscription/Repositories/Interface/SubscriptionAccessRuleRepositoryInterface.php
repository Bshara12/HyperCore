<?php

namespace App\Domains\Subscription\Repositories\Interface;

use App\Models\SubscriptionAccessRule;

interface SubscriptionAccessRuleRepositoryInterface
{
  public function findActiveRule(
    ?int $projectId,
    string $eventKey
  ): ?SubscriptionAccessRule;
  public function findActiveRuleByEvent(
    ?int $projectId,
    string $eventKey
  );
}
