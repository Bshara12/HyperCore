<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\AnalyticsService;
use App\Http\Controllers\EcommerceAnalyticsController;
use Mockery\MockInterface;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

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

  private function createAnalyticsRequest(array $params = []): Request
  {
    // دمج المعاملات مع project_id الافتراضي
    $data = array_merge(['project_id' => $this->projectId], $params);
    $request = Request::create('/api/ecommerce/analytics/dummy', 'GET', $data);

    // إخبار الحاوية باستخدام هذه النسخة
    $this->app->instance('request', $request);

    return $request;
  }

  #[Test]
  public function it_can_fetch_sales_summary()
  {
    $mockData = ['total_sales' => 5000, 'orders_count' => 120];

    $this->analyticsServiceMock
      ->shouldReceive('getSalesSummary')
      ->once()
      ->andReturn($mockData);

    $request = $this->createAnalyticsRequest();
    $controller = new EcommerceAnalyticsController($this->analyticsServiceMock);

    $response = $controller->salesSummary($request);
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson(['success' => true, 'data' => $mockData]);
  }

  #[Test]
  public function it_can_fetch_sales_trend()
  {
    $mockData = [['date' => '2026-05-01', 'amount' => 1000]];

    $this->analyticsServiceMock
      ->shouldReceive('getSalesTrend')
      ->once()
      ->andReturn($mockData);

    $request = $this->createAnalyticsRequest(['period' => 'weekly']);
    $controller = new EcommerceAnalyticsController($this->analyticsServiceMock);

    $response = $controller->salesTrend($request);
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson(['success' => true, 'data' => $mockData]);
  }

  #[Test]
  public function it_can_fetch_top_products()
  {
    $mockData = [['product_name' => 'Laravel Book', 'sales' => 50]];

    $this->analyticsServiceMock
      ->shouldReceive('getTopProducts')
      ->once()
      ->andReturn($mockData);

    $request = $this->createAnalyticsRequest(['limit' => 5]);
    $controller = new EcommerceAnalyticsController($this->analyticsServiceMock);

    $response = $controller->topProducts($request);
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson(['success' => true, 'data' => $mockData]);
  }

  #[Test]
  public function it_can_fetch_top_customers()
  {
    $mockData = [['customer_name' => 'John Doe', 'spent' => 1500]];

    $this->analyticsServiceMock
      ->shouldReceive('getTopCustomers')
      ->once()
      ->andReturn($mockData);

    $request = $this->createAnalyticsRequest();
    $controller = new EcommerceAnalyticsController($this->analyticsServiceMock);

    $response = $controller->topCustomers($request);
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson(['success' => true, 'data' => $mockData]);
  }

  #[Test]
  public function it_can_fetch_offers_analytics()
  {
    // 1. تجهيز بيانات وهمية للعروض (مثل عدد مرات استخدام الكوبونات)
    $mockData = [
      ['offer_name' => 'Ramadan Kareem', 'usage_count' => 150, 'discount_total' => 3000],
      ['offer_name' => 'Welcome Gift', 'usage_count' => 45, 'discount_total' => 450]
    ];

    // 2. توقع استدعاء التابع الخاص بالعروض في الـ Service
    $this->analyticsServiceMock
      ->shouldReceive('getOffersAnalytics')
      ->once()
      ->andReturn($mockData);

    // 3. تنفيذ الطلب
    $request = $this->createAnalyticsRequest();
    $controller = new EcommerceAnalyticsController($this->analyticsServiceMock);

    $response = $controller->offersAnalytics($request);
    $testResponse = $this->createTestResponse($response, $request);

    // 4. التحقق
    $testResponse->assertStatus(200)
      ->assertJson([
        'success' => true,
        'data' => $mockData
      ]);
  }

  #[Test]
  public function it_can_fetch_returns_analytics()
  {
    // 1. تجهيز بيانات وهمية للمرتجعات
    $mockData = [
      'total_returned_items' => 12,
      'refunded_amount' => 1200.50,
      'return_rate' => '2.5%'
    ];

    // 2. توقع استدعاء التابع الخاص بالمرتجعات في الـ Service
    $this->analyticsServiceMock
      ->shouldReceive('getReturnsAnalytics')
      ->once()
      ->andReturn($mockData);

    // 3. تنفيذ الطلب مع فلتر محدد (مثلاً آخر 30 يوم)
    $request = $this->createAnalyticsRequest(['period' => 'monthly']);
    $controller = new EcommerceAnalyticsController($this->analyticsServiceMock);

    $response = $controller->returnsAnalytics($request);
    $testResponse = $this->createTestResponse($response, $request);

    // 4. التحقق
    $testResponse->assertStatus(200)
      ->assertJson([
        'success' => true,
        'data' => $mockData
      ]);
  }
}
