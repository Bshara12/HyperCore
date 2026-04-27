<?php

namespace App\Http\Controllers;

use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\Booking\Services\BookingAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingAnalyticsController extends Controller
{
  public function __construct(
    private BookingAnalyticsService $service
  ) {}

  public function overview(Request $request): JsonResponse
  {
    $dto  = AnalyticsFilterDTO::fromRequest($request);
    $data = $this->service->getOverview($dto);

    return response()->json(['success' => true, 'data' => $data]);
  }

  public function trend(Request $request): JsonResponse
  {
    $dto  = AnalyticsFilterDTO::fromRequest($request);
    $data = $this->service->getTrend($dto);

    return response()->json(['success' => true, 'data' => $data]);
  }

  public function resourcePerformance(Request $request): JsonResponse
  {
    $dto  = AnalyticsFilterDTO::fromRequest($request);
    $data = $this->service->getResourcePerformance($dto);

    return response()->json(['success' => true, 'data' => $data]);
  }

  public function cancellations(Request $request): JsonResponse
  {
    $dto  = AnalyticsFilterDTO::fromRequest($request);
    $data = $this->service->getCancellationReport($dto);

    return response()->json(['success' => true, 'data' => $data]);
  }

  public function peakTimes(Request $request): JsonResponse
  {
    $dto  = AnalyticsFilterDTO::fromRequest($request);
    $data = $this->service->getPeakTimes($dto);

    return response()->json(['success' => true, 'data' => $data]);
  }
}
