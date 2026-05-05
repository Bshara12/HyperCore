<?php

namespace App\Domains\Booking\Services;

use App\Domains\Booking\Analytics\Actions\GetBookingOverviewAction;
use App\Domains\Booking\Analytics\Actions\GetBookingTrendAction;
use App\Domains\Booking\Analytics\Actions\GetResourcePerformanceAction;
use App\Domains\Booking\Analytics\Actions\GetCancellationReportAction;
use App\Domains\Booking\Analytics\Actions\GetPeakTimesAction;
use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;

class BookingAnalyticsService
{
  public function __construct(
    private GetBookingOverviewAction      $overviewAction,
    private GetBookingTrendAction         $trendAction,
    private GetResourcePerformanceAction  $resourceAction,
    private GetCancellationReportAction   $cancellationAction,
    private GetPeakTimesAction            $peakTimesAction,
  ) {}

  public function getOverview(AnalyticsFilterDTO $dto): array
  {
    return $this->overviewAction->execute($dto);
  }

  public function getTrend(AnalyticsFilterDTO $dto): array
  {
    return $this->trendAction->execute($dto);
  }

  public function getResourcePerformance(AnalyticsFilterDTO $dto): array
  {
    return $this->resourceAction->execute($dto);
  }

  public function getCancellationReport(AnalyticsFilterDTO $dto): array
  {
    return $this->cancellationAction->execute($dto);
  }

  public function getPeakTimes(AnalyticsFilterDTO $dto): array
  {
    return $this->peakTimesAction->execute($dto);
  }
}
