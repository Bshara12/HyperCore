<?php

namespace App\Http\Controllers;

use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceAnalyticsController extends Controller
{
  public function __construct(protected AnalyticsService $service) {}

  public function summary(Request $request): JsonResponse
  {
    // 1. بناء الـ DTO لمرة واحدة من الـ Request
    $dto = AnalyticsFilterDTO::fromRequest($request);

    // 2. دمج كافة تقارير المتجر الإلكتروني في مصفوفة واحدة
    return response()->json([
      'success' => true,
      'data' => [
        'sales'         => $this->service->getSalesSummary($dto),
        'sales-trend'   => $this->service->getSalesTrend($dto),
        'top-products'  => $this->service->getTopProducts($dto),
        'offers'        => $this->service->getOffersAnalytics($dto),
        'top-customers' => $this->service->getTopCustomers($dto),
        'returns'       => $this->service->getReturnsAnalytics($dto),
      ],
    ]);
  }
}
