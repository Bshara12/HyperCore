<?php

namespace App\Domains\Subscription\Actions\ContentAccess;

use Exception;

use App\Models\ContentAccessMetadata;

use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;

class ShowContentAccessAction
{
    public function __construct(

        private ContentAccessMetadataRepositoryInterface
        $repository
    ) {}

    public function execute(
        int $id
    ): ContentAccessMetadata {

        $metadata = $this->repository
            ->findById($id);

        if (!$metadata) {

            throw new Exception(
                'Content access metadata not found.'
            );
        }

        return $metadata;
    }
}