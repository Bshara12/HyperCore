<?php

namespace App\Domains\Subscription\Actions\ContentAccess;

use App\Domains\Subscription\DTOs\ContentAccess\UpdateContentAccessMetadataDTO;
use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;
use App\Models\ContentAccessMetadata;

class UpdateContentAccessMetadataAction
{
    public function __construct(

        private ContentAccessMetadataRepositoryInterface
        $repository
    ) {}

    public function execute(
        UpdateContentAccessMetadataDTO $dto
    ): ContentAccessMetadata {

        return $this->repository->update(

            $dto->contentAccessMetadata,

            [

                'project_id' =>
                    $dto->projectId,

                'content_type' =>
                    $dto->contentType,

                'content_id' =>
                    $dto->contentId,

                'requires_subscription' =>
                    $dto->requiresSubscription,

                'required_feature' =>
                    $dto->requiredFeature,

                'metadata' =>
                    $dto->metadata
            ]
        );
    }
}