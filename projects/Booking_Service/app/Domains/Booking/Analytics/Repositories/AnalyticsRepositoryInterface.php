<?php

namespace App\Domains\Booking\Analytics\Repositories;

use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;

interface AnalyticsRepositoryInterface
{
    public function getOverview(AnalyticsFilterDTO $dto): array;

    public function getBookingTrend(AnalyticsFilterDTO $dto): array;

    public function getResourcePerformance(AnalyticsFilterDTO $dto): array;

    public function getCancellationReport(AnalyticsFilterDTO $dto): array;

    public function getPeakTimes(AnalyticsFilterDTO $dto): array;

    public function resolveGroupBy(string $period, string $column): string;
}
