<?php

namespace App\Domains\Subscription\Actions\ContentAccess;

use Exception;

use App\Models\ContentAccessMetadata;

use App\Domains\Subscription\DTOs\ContentAccess\ActivateContentAccessDTO;

use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;

class ActivateContentAccessAction
{
  public function __construct(
    private ContentAccessMetadataRepositoryInterface $repository
  ) {}

  public function execute(
    ActivateContentAccessDTO $dto
  ): ContentAccessMetadata {

    $metadata = $dto->contentAccessMetadata;

    if ($metadata->is_active) {

      throw new Exception(
        'Content access is already active.'
      );
    }

    return $this->repository->update(

      $metadata,

      [

        'is_active' => true
      ]
    );
  }
}
