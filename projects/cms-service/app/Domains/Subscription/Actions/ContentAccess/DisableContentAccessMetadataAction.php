<?php

namespace App\Domains\Subscription\Actions\ContentAccess;

use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;
use App\Models\ContentAccessMetadata;

class DisableContentAccessMetadataAction
{
    public function __construct(

        private ContentAccessMetadataRepositoryInterface $repository
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
