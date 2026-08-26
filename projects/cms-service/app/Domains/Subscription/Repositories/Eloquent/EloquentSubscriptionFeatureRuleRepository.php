<?php

namespace App\Domains\Subscription\Repositories\Eloquent;

use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionFeatureRuleRepositoryInterface;
use App\Models\SubscriptionFeatureRule;
use Illuminate\Database\Eloquent\Collection;

class EloquentSubscriptionFeatureRuleRepository implements SubscriptionFeatureRuleRepositoryInterface
{
    public function create(
        CreateFeatureRuleDTO $dto
    ): SubscriptionFeatureRule {

        return SubscriptionFeatureRule::create([

            'project_id' => $dto->projectId,

            'event_key' => $dto->eventKey,

            'feature_key' => $dto->featureKey,

            'action' => $dto->action,

            'reset_type' => $dto->resetType,

            'is_active' => $dto->isActive,

            'metadata' => $dto->metadata,
        ]);
    }

    public function findActiveRulesByEvent(
        ?int $projectId,
        string $eventKey
    ) {

        return SubscriptionFeatureRule::query()
            ->where('project_id', $projectId)
            ->where('event_key', $eventKey)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Every rule of one project — active or not — for the admin dashboard.
     */
    public function findAllForProject(
        int $projectId
    ): Collection {

        return SubscriptionFeatureRule::query()
            ->where('project_id', $projectId)
            ->orderBy('event_key')
            ->orderBy('feature_key')
            ->get();
    }
}
