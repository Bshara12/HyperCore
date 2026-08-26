<?php

namespace App\Domains\Subscription\Actions\Rule;

use App\Domains\Subscription\Repositories\Interface\SubscriptionFeatureRuleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ListFeatureRulesAction
{
    public function __construct(
        private SubscriptionFeatureRuleRepositoryInterface $repository
    ) {}

    public function execute(
        int $projectId
    ): Collection {

        return $this->repository
            ->findAllForProject($projectId);
    }
}
