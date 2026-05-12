<?php

namespace App\Domains\Subscription\DTOs\ContentAccess;

use App\Domains\Subscription\Requests\ContentAccess\CreateContentAccessRequest;

class CreateContentAccessDTO
{
    public function __construct(

        public readonly ?int $projectId,

        public readonly string $contentType,

        public readonly int $contentId,

        public readonly bool $requiresSubscription,

        public readonly ?string $requiredFeature,

        public readonly ?array $metadata
    ) {}

    public static function fromRequest(
        CreateContentAccessRequest $request
    ): self {

        return new self(

            projectId: $request->project_id,

            contentType: $request->content_type,

            contentId: $request->content_id,

            requiresSubscription: $request->boolean(
                'requires_subscription'
            ),

            requiredFeature: $request->required_feature,

            metadata: $request->metadata
        );
    }
}