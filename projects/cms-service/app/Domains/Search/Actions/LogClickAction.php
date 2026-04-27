<?php

namespace App\Domains\Search\Actions;

use App\Domains\Search\DTOs\LogClickDTO;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use App\Domains\Search\Support\UserPreferenceAnalyzer;

class LogClickAction
{
    public function __construct(
        private UserBehaviorRepositoryInterface $repository,
        private UserPreferenceAnalyzer          $analyzer,
    ) {}

    public function execute(LogClickDTO $dto): void
    {
        $this->repository->logClick($dto);

        // مسح الـ cache حتى يُعاد حساب التفضيلات فوراً
        if ($dto->userId !== null) {
            $this->analyzer->invalidateCache($dto->projectId, $dto->userId);
        }
    }
}