<?php

namespace App\Domains\Subscription\Repositories\Interface;

use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;
use App\Models\SubscriptionFeatureRule;
use Illuminate\Database\Eloquent\Collection;

interface SubscriptionFeatureRuleRepositoryInterface
{
    public function create(
        CreateFeatureRuleDTO $dto
    ): SubscriptionFeatureRule;

    public function findActiveRulesByEvent(
        ?int $projectId,
        string $eventKey
    );

    /**
     * Every rule of one project — active or not — for the admin dashboard.
     */
    public function findAllForProject(
        int $projectId
    ): Collection;
}
