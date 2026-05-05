<?php

namespace App\Domains\CMS\Analytics\Actions;

use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class GetTopRatedEntriesAction
{
  public function __construct(
    private AnalyticsRepositoryInterface $repository
  ) {}

  public function execute(AnalyticsFilterDTO $dto): array
  {
    return Cache::remember(
      "analytics:project:{$dto->projectId}:top_rated:{$dto->limit}",
      CacheKeys::TTL_SHORT,
      fn() => $this->repository->getTopRatedEntries($dto)
    );
  }
}
