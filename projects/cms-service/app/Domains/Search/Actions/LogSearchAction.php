<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\DTOs\LogSearchDTO;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;

class LogSearchAction
{
    public function __construct(
        private UserBehaviorRepositoryInterface $repository,
    ) {}

    public function execute(LogSearchDTO $dto): int
    {
        return $this->repository->logSearch($dto);
    }
}