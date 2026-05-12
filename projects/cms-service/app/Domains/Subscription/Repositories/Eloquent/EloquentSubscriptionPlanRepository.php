<?php

namespace App\Domains\Subscription\Repositories\Eloquent;

use App\Domains\Subscription\DTOs\Plan\CreatePlanDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionPlanRepositoryInterface;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class EloquentSubscriptionPlanRepository
    implements SubscriptionPlanRepositoryInterface
{
    public function createWithFeatures(
        CreatePlanDTO $dto
    ): SubscriptionPlan {

        return DB::transaction(function () use ($dto) {

            $plan = SubscriptionPlan::create([
                'project_id' => $dto->projectId,
                'name' => $dto->name,
                'slug' => $dto->slug,
                'description' => $dto->description,
                'price' => $dto->price,
                'currency' => $dto->currency,
                'duration_days' => $dto->durationDays,
                'is_active' => $dto->isActive,
                'metadata' => $dto->metadata
            ]);

            if (!empty($dto->features)) {

                $rows = [];

                foreach ($dto->features as $feature) {

                    $rows[] = [
                        'plan_id' => $plan->id,
                        'feature_key' => $feature['feature_key'],
                        'feature_type' => $feature['feature_type'],
                        'feature_value' => json_encode(
                            $feature['feature_value']
                        ),
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }

                SubscriptionFeature::insert($rows);
            }

            return $plan->load('features');
        });
    }

    public function slugExists(
        ?int $projectId,
        string $slug
    ): bool {

        return SubscriptionPlan::query()
            ->where('project_id', $projectId)
            ->where('slug', $slug)
            ->exists();
    }
}