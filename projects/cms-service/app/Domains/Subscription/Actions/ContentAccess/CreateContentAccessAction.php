<?php

namespace App\Domains\Subscription\Actions\ContentAccess;

use App\Domains\Subscription\DTOs\ContentAccess\CreateContentAccessDTO;
use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;
use App\Domains\Subscription\Repositories\Interface\ContentTypeResolverInterface;
use App\Models\ContentAccessMetadata;

class CreateContentAccessAction
{
    public function __construct(
        private ContentAccessMetadataRepositoryInterface $repository,
        private ContentTypeResolverInterface $contentTypeResolver
    ) {}

    public function execute(
        CreateContentAccessDTO $dto
    ): ContentAccessMetadata {

        /*
        |--------------------------------------------------------------
        | Resolve content_type from content_id.
        |
        | Throws ContentEntryNotFoundException if content_id is invalid.
        | (Normally the Request already validates exists:data_entries,
        |  but the resolver is the authoritative domain-level check.)
        |--------------------------------------------------------------
        */
        $contentType = $this->contentTypeResolver
            ->resolve($dto->contentId);

        return $this->repository
            ->createWithFeatures(
                data: [
                    'project_id'            => $dto->projectId,
                    'content_type'          => $contentType,
                    'content_id'            => $dto->contentId,
                    'requires_subscription' => $dto->requiresSubscription,
                    'metadata'              => $dto->metadata,
                    'is_active'             => true,
                ],
                features: $dto->features
            );
    }
}