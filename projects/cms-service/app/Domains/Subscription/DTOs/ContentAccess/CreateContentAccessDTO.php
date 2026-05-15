<?php

namespace App\Domains\Subscription\DTOs\ContentAccess;

use App\Domains\Subscription\Requests\ContentAccess\CreateContentAccessRequest;

class CreateContentAccessDTO
{
    public function __construct(

        public readonly ?int $projectId,

        public readonly int $contentId,

        public readonly bool $requiresSubscription,

        /**
         * List of allowed feature keys.
         * User needs at least ONE to access the content.
         *
         * @var string[]
         */
        public readonly array $features,

        public readonly ?array $metadata
    ) {}

    public static function fromRequest(
        CreateContentAccessRequest $request
    ): self {

        return new self(

            projectId: $request->project_id,

            contentId: (int) $request->content_id,

            requiresSubscription: $request->boolean(
                'requires_subscription'
            ),

            features: $request->input('features', []),

            metadata: $request->input('metadata'),
        );
    }
}