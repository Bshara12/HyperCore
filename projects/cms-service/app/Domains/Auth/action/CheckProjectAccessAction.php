<?php

namespace App\Domains\Auth\Action;

use App\Domains\Auth\DTOs\CheckProjectAccessDto;
use App\Domains\Auth\Repository\Interface\ProjectUserRepositoryInterface;

class CheckProjectAccessAction
{
    public function __construct(
        private ProjectUserRepositoryInterface $repo
    ) {}

    public function execute(CheckProjectAccessDto $dto): bool
    {
        return $this->repo->exists(
            $dto->userId,
            $dto->projectKey
        );
    }
}