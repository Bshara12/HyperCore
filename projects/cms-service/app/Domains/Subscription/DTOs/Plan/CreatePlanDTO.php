<?php

namespace App\Domains\Subscription\DTOs\Plan;

use App\Domains\Subscription\Requests\Plan\CreatePlanRequest;

class CreatePlanDTO
{
    public function __construct(
        public readonly ?int $projectId,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly float $price,
        public readonly string $currency,
        public readonly int $durationDays,
        public readonly bool $isActive,
        public readonly array $features,
        public readonly ?array $metadata
    ) {}

    public static function fromRequest(
        CreatePlanRequest $request
    ): self {

        return new self(
            projectId: $request->project_id,
            name: $request->name,
            slug: $request->slug,
            description: $request->description,
            price: (float) $request->price,
            currency: $request->currency,
            durationDays: (int) $request->duration_days,
            isActive: $request->boolean('is_active', true),
            features: $request->features ?? [],
            metadata: $request->metadata
        );
    }
}