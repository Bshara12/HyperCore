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
    // 1. بناء الـ DTO لمرة واحدة واستخدامه في كل التوابع
    $dto = AnalyticsFilterDTO::fromRequest($request);

    // 2. جمع كافة البيانات من الخدمات في طلب واحد
    return response()->json([
      'success' => true,
      'data' => [
        'summary'       => $this->service->getOverview($dto),
        'trend'         => $this->service->getTrend($dto),
        'resources'     => $this->service->getResourcePerformance($dto),
        'cancellations' => $this->service->getCancellationReport($dto),
        'peak-times'    => $this->service->getPeakTimes($dto),
      ],
    ]);
  }
}
