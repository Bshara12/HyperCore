<?php

namespace App\Domains\E_Commerce\Analytics\Actions;

use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Domains\E_Commerce\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class GetReturnsAnalyticsAction
{
  public function __construct(
    private AnalyticsRepositoryInterface $repository
  ) {}

  public function execute(AnalyticsFilterDTO $dto): array
  {
    return Cache::remember(
      "analytics:ecommerce:project:{$dto->projectId}:returns:{$dto->period}:{$dto->from}:{$dto->to}",
      CacheKeys::TTL_SHORT,
      fn() => $this->repository->getReturnsAnalytics($dto)
    );
  }
}
