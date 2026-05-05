<?php

namespace App\Domains\CMS\Analytics\Actions;

use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class GetAdminOverviewAction
{
  public function __construct(
    private AnalyticsRepositoryInterface $repository
  ) {}

  public function execute(AdminOverviewDTO $dto): array
  {
    return Cache::remember(
      "analytics:admin:overview:{$dto->from}:{$dto->to}",
      CacheKeys::TTL_SHORT,
      fn() => $this->repository->getAdminOverview($dto->from, $dto->to)
    );
  }
}
