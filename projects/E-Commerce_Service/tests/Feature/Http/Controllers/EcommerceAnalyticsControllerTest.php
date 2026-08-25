<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\AnalyticsService;
use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Analytics\Requests\AnalyticsFilterRequest;
use App\Http\Controllers\EcommerceAnalyticsController;
use Mockery\MockInterface;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Mockery;

class EcommerceAnalyticsControllerTest extends TestCase
{
  use WithoutMiddleware;

  private MockInterface $analyticsServiceMock;
  private int $projectId = 2;

  protected function setUp(): void
  {
    parent::setUp();
    // عمل Mock للخدمة
    $this->analyticsServiceMock = $this->mock(AnalyticsService::class);
  }

  /**
   * summary() now type-hints AnalyticsFilterRequest so the date/limit filters
   * are validated before they reach the SQL and the cache key.
   *
   * The 'project' key mirrors what ResolveProject merges into the request; the
   * DTO reads the project from there instead of trusting a client-supplied
   * project_id.
   */
  private function createAnalyticsRequest(array $params = []): AnalyticsFilterRequest
  {
    $data = array_merge([
      'project_id' => $this->projectId,
      'project' => ['id' => $this->projectId, 'enabled_modules' => ['ecommerce']],
    ], $params);

    $base = Request::create('/api/ecommerce/analytics/summary', 'GET', $data);

    $request = AnalyticsFilterRequest::createFrom($base);
    $request->setContainer($this->app);

    // إخبار الحاوية باستخدام هذه النسخة
    $this->app->instance('request', $request);

    return $request;
  }

  #[Test]
  public function it_can_fetch_aggregated_analytics_summary()
  {
    // 1. تجهيز بيانات وهمية لكل شق في التقارير
    $mockSales = ['total_sales' => 5000, 'orders_count' => 120];
    $mockTrend = [['date' => '2026-05-01', 'amount' => 1000]];
    $mockProducts = [['product_name' => 'Laravel Book', 'sales' => 50]];
    $mockOffers = [['offer_name' => 'Ramadan Kareem', 'usage_count' => 150]];
    $mockCustomers = [['customer_name' => 'John Doe', 'spent' => 1500]];
    $mockReturns = ['total_returned_items' => 12, 'refunded_amount' => 1200.50];

    // 2. إعداد توقعات الـ Mock للخدمة (تتوقع تمرير DTO)
    $this->analyticsServiceMock
      ->shouldReceive('getSalesSummary')
      ->once()
      ->with(Mockery::type(AnalyticsFilterDTO::class))
      ->andReturn($mockSales);

    $this->analyticsServiceMock
      ->shouldReceive('getSalesTrend')
      ->once()
      ->with(Mockery::type(AnalyticsFilterDTO::class))
      ->andReturn($mockTrend);

    $this->analyticsServiceMock
      ->shouldReceive('getTopProducts')
      ->once()
      ->with(Mockery::type(AnalyticsFilterDTO::class))
      ->andReturn($mockProducts);

    $this->analyticsServiceMock
      ->shouldReceive('getOffersAnalytics')
      ->once()
      ->with(Mockery::type(AnalyticsFilterDTO::class))
      ->andReturn($mockOffers);

    $this->analyticsServiceMock
      ->shouldReceive('getTopCustomers')
      ->once()
      ->with(Mockery::type(AnalyticsFilterDTO::class))
      ->andReturn($mockCustomers);

    $this->analyticsServiceMock
      ->shouldReceive('getReturnsAnalytics')
      ->once()
      ->with(Mockery::type(AnalyticsFilterDTO::class))
      ->andReturn($mockReturns);

    // 3. تنفيذ الطلب على دالة summary الجديدة
    $request = $this->createAnalyticsRequest(['period' => 'monthly']);
    $controller = new EcommerceAnalyticsController($this->analyticsServiceMock);

    $response = $controller->summary($request);
    $testResponse = $this->createTestResponse($response, $request);

    // 4. التحقق من هيكلية الاستجابة المدمجة
    $testResponse->assertStatus(200)
      ->assertJson([
        'success' => true,
        'data' => [
          'sales'         => $mockSales,
          'sales-trend'   => $mockTrend,
          'top-products'  => $mockProducts,
          'offers'        => $mockOffers,
          'top-customers' => $mockCustomers,
          'returns'       => $mockReturns,
        ],
      ]);
  }
}
