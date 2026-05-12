<?php

namespace App\Domains\Subscription\Actions\ContentAccess;

use App\Models\ContentAccessMetadata;

use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;

class DisableContentAccessMetadataAction
{
  public function __construct(

    private ContentAccessMetadataRepositoryInterface
    $repository
  ) {}

  public function execute(
    ContentAccessMetadata $metadata
  ): ContentAccessMetadata {
    if (! $metadata->is_active) {

      return $metadata;
    }

    return $this->repository
      ->disable($metadata);
  }
}
