<?php

namespace App\Domains\Subscription\Actions\ContentAccess;

use App\Models\ContentAccessMetadata;

use App\Domains\Subscription\DTOs\ContentAccess\CreateContentAccessDTO;

use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;

class CreateContentAccessAction
{
  public function __construct(

    private ContentAccessMetadataRepositoryInterface $repository
  ) {}

  public function execute(
    CreateContentAccessDTO $dto
  ): ContentAccessMetadata {

    return $this->repository
      ->create([

        'project_id' => $dto->projectId,

        'content_type' => $dto->contentType,

        'content_id' => $dto->contentId,

        'requires_subscription' => $dto->requiresSubscription,

        'required_feature' => $dto->requiredFeature,

        'metadata' => $dto->metadata,
        'is_active' => true,
      ]);
  }
}
