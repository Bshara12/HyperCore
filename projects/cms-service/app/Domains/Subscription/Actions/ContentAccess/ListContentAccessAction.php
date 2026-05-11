<?php

namespace App\Domains\Subscription\Actions\ContentAccess;

use App\Domains\Subscription\Repositories\Interface\ContentAccessMetadataRepositoryInterface;

class ListContentAccessAction
{
    public function __construct(

        private ContentAccessMetadataRepositoryInterface
        $repository
    ) {}

    public function execute(
        ?int $projectId = null
    ) {

        return $this->repository
            ->paginate($projectId);
    }
}