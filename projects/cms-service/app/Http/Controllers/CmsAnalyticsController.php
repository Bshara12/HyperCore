<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Services\AnalyticsService;
use App\Services\BookingAnalyticsClient;
use App\Services\EcommerceAnalyticsClient;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsAnalyticsController extends Controller
{
  public function __construct(protected AnalyticsService $service) {}

  // public function adminOverview(Request $request): JsonResponse
  // {
  //   $dto = AdminOverviewDTO::fromRequest($request);
  //   $data = $this->service->adminOverview($dto);

  //   return response()->json([
  //     'success' => true,
  //     'period' => ['from' => $dto->from, 'to' => $dto->to],
  //     'data' => $data,
  //   ]);
  // }

  // public function projectsGrowth(Request $request): JsonResponse
  // {
  //   $dto = AdminOverviewDTO::fromRequest($request);
  //   // $data = $this->service->projectsGrowth($dto);
  //   $data['admin-overview'] = $this->service->adminOverview($dto);
  //   $data['projects-growth'] = $this->service->projectsGrowth($dto);

  //   return response()->json([
  //     'success' => true,
  //     'data' => $data,
  //   ]);
  // }

  // public function contentSummary(Request $request): JsonResponse
  // {
  //   $dto = AnalyticsFilterDTO::fromRequest($request);
  //   $data = $this->service->contentSummary($dto);

  //   return response()->json([
  //     'success' => true,
  //     'data' => $data,
  //   ]);
  // }

  // public function contentGrowth(Request $request): JsonResponse
  // {
  //   $dto = AnalyticsFilterDTO::fromRequest($request);
  //   $data = $this->service->contentGrowth($dto);

  //   return response()->json([
  //     'success' => true,
  //     'data' => $data,
  //   ]);
  // }

  // public function topRated(Request $request): JsonResponse
  // {
  //   $dto = AnalyticsFilterDTO::fromRequest($request);
  //   $data = $this->service->topRatedEntries($dto);

  //   return response()->json([
  //     'success' => true,
  //     'data' => $data,
  //   ]);
  // }

  // public function ratingsReport(Request $request): JsonResponse
  // {
  //   $dto = AnalyticsFilterDTO::fromRequest($request);
  //   $data = $this->service->ratingsReport($dto);

  //   return response()->json([
  //     'success' => true,
  //     'data' => $data,
  //   ]);
  // }

  public function adminOverview(Request $request): JsonResponse
  {
    $dto = AdminOverviewDTO::fromRequest($request);

    return response()->json([
      'success' => true,
      'period'  => ['from' => $dto->from, 'to' => $dto->to],
      'data'    => [
        'platform-overview'  => $this->service->adminOverview($dto),
        'projects-growth' => $this->service->projectsGrowth($dto),
      ],
    ]);
  }

  public function projectOverview(
    Request $request,
    EcommerceAnalyticsClient $ecommerceClient,
    BookingAnalyticsClient $bookingClient
  ): JsonResponse {
    // 1. بناء الـ DTO من الطلب
    $dto = AnalyticsFilterDTO::fromRequest($request);

    // 2. جلب البيانات الأساسية (إذا حدث خطأ هنا سيتوقف الكود تلقائياً)
    $data = [
      'content-summary' => $this->service->contentSummary($dto),
      'content-growth'  => $this->service->contentGrowth($dto),
      'top-rated'       => $this->service->topRatedEntries($dto),
      'ratings-report'  => $this->service->ratingsReport($dto),
    ];

    // 3. استخراج التوكن والمعلومات المطلوبة للخدمات المصغرة (Microservices)
    // ملاحظة: تأكد إن كان الـ DTO يتيح الوصول للبيانات كـ Array أو Object وقابليتها للتعديل
    $token = $request->bearerToken();
    $project = $dto->project ?? [];
    $projectId = $project['public_id'] ?? null;
    $enabledModules = $project['enabled_modules'] ?? [];

    // الفلاتر المطلوبة للـ APIs الخارجية
    $filters = [
      'from'   => $dto->from,
      'to'     => $dto->to,
      'period' => $dto->period,
    ];

    // 4. التحقق من وجود موديل المتجر الإلكتروني ecommerce
    if (in_array('ecommerce', $enabledModules) && $projectId) {
      try {
        $data['ecommerce'] = $ecommerceClient->getSummary($token, $projectId, $filters);
      } catch (Exception $e) {
        // return response()->json([
        //   'success' => false,
        //   'message' => "Error fetching ecommerce analytics: " . $e->getMessage(),
        // ], 500);
        report($e);
        $data['ecommerce'] = null;
      }
    }

    // 5. التحقق من وجود موديل الحجوزات booking
    if (in_array('booking', $enabledModules) && $projectId) {
      try {
        $data['booking'] = $bookingClient->getOverview($token, $projectId, $filters);
      } catch (Exception $e) {
        // return response()->json([
        //   'success' => false,
        //   'message' => "Error fetching booking analytics: " . $e->getMessage(),
        // ], 500);
        report($e);
        $data['booking'] = null;
      }
    }

    // 6. إرجاع النتيجة النهائية المدمجة
    return response()->json([
      'success' => true,
      'data'    => $data,
    ]);
  }
}
