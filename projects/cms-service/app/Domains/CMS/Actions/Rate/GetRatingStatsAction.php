<?php

namespace App\Domains\CMS\Actions\Rate;

use App\Domains\CMS\DTOs\Rate\GetRatingStatsDTO;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class GetRatingStatsAction
{
  public function __construct(
    private ProjectRepositoryInterface $projects,
    private DataEntryRepositoryInterface $dataEntries
  ) {}

  public function execute(GetRatingStatsDTO $dto): array
  {
    return Cache::remember(
      CacheKeys::ratingStats($dto->rateableType, $dto->rateableId),
      CacheKeys::TTL_MEDIUM,
      fn() => match ($dto->rateableType) {
        'project' => $this->projects->getRatingStats($dto->rateableId),
        'data'    => $this->dataEntries->getRatingStats($dto->rateableId),
      }
    );
  }
}
