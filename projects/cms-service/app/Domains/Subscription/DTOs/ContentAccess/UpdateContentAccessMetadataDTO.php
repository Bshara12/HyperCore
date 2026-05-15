<?php

namespace App\Domains\Subscription\DTOs\ContentAccess;

use App\Domains\Subscription\Requests\ContentAccess\UpdateContentAccessMetadataRequest;
use App\Models\ContentAccessMetadata;

class UpdateContentAccessMetadataDTO
{
    public function __construct(

        public readonly ?int $projectId,

        /*
         * content_id is nullable on update.
         * If null, the Action keeps the existing content_id from the model.
         */
        public readonly ?int $contentId,

        public readonly bool $requiresSubscription,

        /**
         * Full replacement list of allowed feature keys.
         * Empty array removes all feature restrictions.
         *
         * @var string[]
         */
        public readonly array $features,

        public readonly bool $isActive,

        public readonly ?array $metadata,

        public readonly ContentAccessMetadata $contentAccessMetadata
    ) {}

    public static function fromRequest(
        UpdateContentAccessMetadataRequest $request,
        ContentAccessMetadata $metadata
    ): self {

        return new self(

            projectId: $request->project_id,

            contentId: $request->input('content_id') !== null
                ? (int) $request->content_id
                : null,

            requiresSubscription: $request->boolean(
                'requires_subscription'
            ),

            features: $request->input('features', []),

            isActive: $request->boolean('is_active', true),

            metadata: $request->input('metadata'),

            contentAccessMetadata: $metadata,
        );
    }
}