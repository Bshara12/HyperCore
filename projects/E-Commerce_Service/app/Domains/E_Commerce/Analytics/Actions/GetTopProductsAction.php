<?php

namespace App\Domains\E_Commerce\Analytics\Actions;

use App\Domains\E_Commerce\Support\CacheKeys;
use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class GetTopProductsAction
{
  public function __construct(
    private AnalyticsRepositoryInterface $repository
  ) {}

  public function execute(AnalyticsFilterDTO $dto): array
  {
    return Cache::remember(
      "analytics:ecommerce:project:{$dto->projectId}:top_products:{$dto->limit}:{$dto->from}:{$dto->to}",
      CacheKeys::TTL_SHORT,
      fn() => $this->repository->getTopProducts($dto)
    );
  }
}
