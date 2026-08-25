<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Analytics\Requests\AnalyticsFilterRequest;
use App\Domains\CMS\Services\AnalyticsService;
use App\Services\BookingAnalyticsClient;
use App\Services\EcommerceAnalyticsClient;
use Exception;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

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
    AnalyticsFilterRequest $request,
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
    $token = $request->bearerToken();
    $project = $dto->project;                     // non-nullable on the DTO
    $projectId = $project->public_id;
    $enabledModules = $project->enabled_modules ?? [];

    // الفلاتر المطلوبة للـ APIs الخارجية
    $filters = [
      'from'   => $dto->from,
      'to'     => $dto->to,
      'period' => $dto->period,
    ];

    /*
     | 4 + 5. الموديولات الخارجية.
     |
     | كانت تُستدعى بالتسلسل — أي أن زمن الاستجابة هو مجموع الخدمتين. صارت
     | متوازية، والفشل يظهر صراحةً في partial_failures بدل أن يُبتلع في null
     | لا يميّزه المستهلك عن «لا توجد بيانات». هكذا ظلّ كسر مسار Booking
     | (404) غير مرئي.
     */
    $partialFailures = [];

    $specs = [];

    if (in_array('ecommerce', $enabledModules) && $projectId) {
      $specs['ecommerce'] = $ecommerceClient->summaryRequest($token, $projectId, $filters);
    }

    if (in_array('booking', $enabledModules) && $projectId) {
      $specs['booking'] = $bookingClient->overviewRequest($token, $projectId, $filters);
    }

    foreach ($this->fetchConcurrently($specs) as $module => $outcome) {
      if ($outcome['ok']) {
        $data[$module] = $outcome['value'];

        continue;
      }

      report($outcome['error']);

      $data[$module] = null;
      $partialFailures[$module] = $outcome['error']->getMessage();
    }

    // 6. إرجاع النتيجة النهائية المدمجة
    $payload = [
      'success' => true,
      'data'    => $data,
    ];

    if ($partialFailures !== []) {
      $payload['partial_failures'] = $partialFailures;
    }

    return response()->json($payload);
  }

  /**
   * Issue the module requests side by side instead of one after the other.
   *
   * Http::pool dispatches them on Guzzle's async transport, so wall-clock is
   * the slowest single call rather than the sum. A failure in one module never
   * cancels the other — each outcome is reported independently.
   *
   * @param  array<string, array{url: string, headers: array<string,string>, query: array<string,mixed>}>  $specs
   * @return array<string, array{ok: bool, value?: mixed, error?: \Throwable}>
   */
  private function fetchConcurrently(array $specs): array
  {
    if ($specs === []) {
      return [];
    }

    $keys = array_keys($specs);

    $responses = Http::pool(function (Pool $pool) use ($specs) {
      foreach ($specs as $key => $spec) {
        $pool->as($key)
          ->withHeaders($spec['headers'])
          ->timeout(60)
          ->get($spec['url'], $spec['query']);
      }
    });

    $results = [];

    foreach ($keys as $key) {
      $response = $responses[$key] ?? null;

      // A connection-level failure comes back as the exception itself.
      if ($response instanceof Throwable) {
        $results[$key] = ['ok' => false, 'error' => $response];

        continue;
      }

      if ($response === null || ! $response->successful()) {
        $status = $response?->status() ?? 0;
        $body = $response ? mb_substr($response->body(), 0, 300) : 'no response';

        $results[$key] = [
          'ok' => false,
          'error' => new Exception("{$key} analytics request failed with HTTP {$status}: {$body}"),
        ];

        continue;
      }

      $json = $response->json();

      $results[$key] = ['ok' => true, 'value' => $json['data'] ?? $json];
    }

    return $results;
  }
}
