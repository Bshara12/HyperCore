<?php

namespace App\Domains\Booking\Analytics\Actions;

use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\Booking\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

class GetBookingOverviewAction
{
    public function __construct(
        private AnalyticsRepositoryInterface $repository
    ) {}

    public function execute(AnalyticsFilterDTO $dto): array
    {
        return Cache::remember(
            "analytics:booking:project:{$dto->projectId}:overview:{$dto->from}:{$dto->to}",
            CacheKeys::TTL_SHORT,
            fn () => $this->repository->getOverview($dto)
        );
    }
}
