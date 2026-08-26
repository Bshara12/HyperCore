<?php

namespace App\Domains\Subscription\Repositories\Eloquent;

use App\Domains\Subscription\Repositories\Interface\SubscriptionAccessRuleRepositoryInterface;
use App\Models\SubscriptionAccessRule;

class EloquentSubscriptionAccessRuleRepository implements SubscriptionAccessRuleRepositoryInterface
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

    /**
     * Alias kept for the AuthorizeEventAction call site.
     * Delegates instead of duplicating the query.
     */
    public function findActiveRuleByEvent(
        ?int $projectId,
        string $eventKey
    ): ?SubscriptionAccessRule {

        return $this->findActiveRule(
            $projectId,
            $eventKey
        );
    }
}
