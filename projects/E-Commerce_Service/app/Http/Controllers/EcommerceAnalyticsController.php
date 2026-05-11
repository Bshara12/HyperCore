<?php

namespace App\Http\Controllers;

use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceAnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $service) {}

    public function salesSummary(
        Request $request
    ): JsonResponse {
        $dto = AnalyticsFilterDTO::fromRequest($request);
        $data = $this->service->getSalesSummary($dto);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function salesTrend(
        Request $request
    ): JsonResponse {
        $dto = AnalyticsFilterDTO::fromRequest($request);
        $data = $this->service->getSalesTrend($dto);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function topProducts(
        Request $request
    ): JsonResponse {
        $dto = AnalyticsFilterDTO::fromRequest($request);
        $data = $this->service->getTopProducts($dto);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function offersAnalytics(
        Request $request
    ): JsonResponse {
        $dto = AnalyticsFilterDTO::fromRequest($request);
        $data = $this->service->getOffersAnalytics($dto);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function topCustomers(
        Request $request
    ): JsonResponse {
        $dto = AnalyticsFilterDTO::fromRequest($request);
        $data = $this->service->getTopCustomers($dto);

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function returnsAnalytics(
        Request $request
    ): JsonResponse {
        $dto = AnalyticsFilterDTO::fromRequest($request);
        $data = $this->service->getReturnsAnalytics($dto);

        return response()->json(['success' => true, 'data' => $data]);
    }
}
