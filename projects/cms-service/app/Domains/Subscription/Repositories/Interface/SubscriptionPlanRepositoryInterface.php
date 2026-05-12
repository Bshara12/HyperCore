<?php

namespace App\Domains\Subscription\Repositories\Interface;

use App\Domains\Subscription\DTOs\Plan\CreatePlanDTO;
use App\Models\SubscriptionPlan;

interface SubscriptionPlanRepositoryInterface
{
    public function createWithFeatures(
        CreatePlanDTO $dto
    ): SubscriptionPlan;

    public function slugExists(
        ?int $projectId,
        string $slug
    ): bool;
}