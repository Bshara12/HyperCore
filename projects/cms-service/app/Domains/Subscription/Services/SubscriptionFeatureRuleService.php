<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\Rule\CreateFeatureRuleAction;
use App\Domains\Subscription\Actions\Rule\ListFeatureRulesAction;
use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;
use App\Models\SubscriptionFeatureRule;
use Illuminate\Database\Eloquent\Collection;

class SubscriptionFeatureRuleService
{
    public function __construct(
        private CreateFeatureRuleAction $createAction,
        private ListFeatureRulesAction $listAction
    ) {}

    public function create(
        CreateFeatureRuleDTO $dto
    ): SubscriptionFeatureRule {

        return $this->createAction
            ->execute($dto);
    }

    public function listForProject(
        int $projectId
    ): Collection {

        return $this->listAction
            ->execute($projectId);
    }
}
