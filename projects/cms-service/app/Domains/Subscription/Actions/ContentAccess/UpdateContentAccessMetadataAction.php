<?php

namespace App\Domains\Subscription\Actions\ContentAccess;

use App\Domains\Subscription\DTOs\ContentAccess\UpdateContentAccessMetadataDTO;
use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;
use App\Domains\Subscription\Repositories\Interface\ContentTypeResolverInterface;
use App\Models\ContentAccessMetadata;

class UpdateContentAccessMetadataAction
{
    public function __construct(
        private ContentAccessMetadataRepositoryInterface $repository,
        private ContentTypeResolverInterface $contentTypeResolver
    ) {}

    public function execute(
        UpdateContentAccessMetadataDTO $dto
    ): ContentAccessMetadata {

        $existing = $dto->contentAccessMetadata;

        /*
        |--------------------------------------------------------------
        | Resolve content_type:
        |
        | - If content_id changed → resolve fresh content_type.
        | - If content_id not provided (null) → keep existing values.
        |
        | This avoids an unnecessary DB query when the caller only
        | wants to update requires_subscription or features.
        |--------------------------------------------------------------
        */
        if (
            $dto->contentId !== null
            && $dto->contentId !== $existing->content_id
        ) {
            $contentId   = $dto->contentId;
            $contentType = $this->contentTypeResolver->resolve($contentId);
        } else {
            $contentId   = $existing->content_id;
            $contentType = $existing->content_type;
        }

        return $this->repository
            ->updateWithFeatures(
                metadata: $existing,
                data: [
                    'project_id'            => $dto->projectId,
                    'content_type'          => $contentType,
                    'content_id'            => $contentId,
                    'requires_subscription' => $dto->requiresSubscription,
                    'metadata'              => $dto->metadata,
                    'is_active'             => $dto->isActive,
                ],
                features: $dto->features
            );
    }
}