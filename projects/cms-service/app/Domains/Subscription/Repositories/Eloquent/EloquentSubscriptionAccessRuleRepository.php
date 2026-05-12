<?php

namespace App\Domains\Subscription\Repositories\Eloquent;

use App\Models\SubscriptionAccessRule;
use App\Domains\Subscription\Repositories\Interface\SubscriptionAccessRuleRepositoryInterface;

class EloquentSubscriptionAccessRuleRepository
implements SubscriptionAccessRuleRepositoryInterface
{
  public function findActiveRule(
    ?int $projectId,
    string $eventKey
  ): ?SubscriptionAccessRule {

    return SubscriptionAccessRule::query()

      ->where('project_id', $projectId)

      ->where('event_key', $eventKey)

      ->where('is_active', true)

      ->first();
  }
  public function findActiveRuleByEvent(
    ?int $projectId,
    string $eventKey
  ) {

    return SubscriptionAccessRule::query()

      ->where('project_id', $projectId)

      ->where('event_key', $eventKey)

      ->where('is_active', true)

      ->first();
  }
}
