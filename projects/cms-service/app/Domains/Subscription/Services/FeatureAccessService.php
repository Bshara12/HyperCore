<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\Feature\CheckFeatureAccessAction;
use App\Domains\Subscription\DTOs\Feature\CheckFeatureAccessDTO;

class FeatureAccessService
{
    public function __construct(
        private CheckFeatureAccessAction $action
    ) {}

    public function hasAccess(
        CheckFeatureAccessDTO $dto
    ): bool {

        return $this->action
            ->execute($dto);
    }
}


/*
how to use:

$hasAccess = app(FeatureAccessService::class)
    ->hasAccess(
        new CheckFeatureAccessDTO(
            userId: $userId,
            projectId: $projectId,
            featureKey: 'premium_articles'
        )
    );

if (!$hasAccess) {

    abort(403, 'Feature unavailable.');
}
    
*/