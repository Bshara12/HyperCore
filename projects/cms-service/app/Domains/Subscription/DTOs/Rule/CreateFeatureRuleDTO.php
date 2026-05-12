<?php

namespace App\Domains\Subscription\DTOs\Rule;

use App\Domains\Subscription\Requests\Rule\CreateFeatureRuleRequest;

class CreateFeatureRuleDTO
{
    public function __construct(

        public readonly ?int $projectId,

        public readonly string $eventKey,

        public readonly string $featureKey,

        public readonly string $action,

        public readonly string $resetType,

        public readonly bool $isActive,

        public readonly ?array $metadata
    ) {}

    public static function fromRequest(
        CreateFeatureRuleRequest $request
    ): self {

        return new self(

            projectId: $request->project_id,

            eventKey: $request->event_key,

            featureKey: $request->feature_key,

            action: $request->action,

            resetType: $request->reset_type,

            isActive: $request->boolean(
                'is_active',
                true
            ),

            metadata: $request->metadata
        );
    }
}