<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsAnalyticsController extends Controller
{
  public function __construct(protected AnalyticsService $service) {}

  public function adminOverview(Request $request): JsonResponse
  {
    $dto = AdminOverviewDTO::fromRequest($request);
    $data = $this->service->adminOverview($dto);

    return response()->json([
      'success' => true,
      'period'  => ['from' => $dto->from, 'to' => $dto->to],
      'data'    => $data,
    ]);
  }

  public function projectsGrowth(Request $request): JsonResponse
  {
    $dto  = AdminOverviewDTO::fromRequest($request);
    $data = $this->service->projectsGrowth($dto);

    return response()->json([
      'success' => true,
      'data'    => $data,
    ]);
  }

  public function contentSummary(Request $request): JsonResponse
  {
    $dto  = AnalyticsFilterDTO::fromRequest($request);
    $data = $this->service->contentSummary($dto);

    return response()->json([
      'success' => true,
      'data'    => $data,
    ]);
  }

  public function contentGrowth(Request $request): JsonResponse
  {
    $dto  = AnalyticsFilterDTO::fromRequest($request);
    $data = $this->service->contentGrowth($dto);

    return response()->json([
      'success' => true,
      'data'    => $data,
    ]);
  }

  public function topRated(Request $request): JsonResponse
  {
    $dto  = AnalyticsFilterDTO::fromRequest($request);
    $data = $this->service->topRatedEntries($dto);

    return response()->json([
      'success' => true,
      'data'    => $data,
    ]);
  }

  public function ratingsReport(Request $request): JsonResponse
  {
    $dto  = AnalyticsFilterDTO::fromRequest($request);
    $data = $this->service->ratingsReport($dto);

    return response()->json([
      'success' => true,
      'data'    => $data,
    ]);
  }
}
