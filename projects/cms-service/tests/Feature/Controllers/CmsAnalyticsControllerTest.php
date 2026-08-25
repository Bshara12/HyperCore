<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\CMS\Services\AnalyticsService;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Analytics\Requests\AnalyticsFilterRequest;
use App\Http\Controllers\CmsAnalyticsController;
use Mockery\MockInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class CmsAnalyticsControllerTest extends TestCase
{
  use WithoutMiddleware;

  private MockInterface $analyticsServiceMock;
  private MockInterface $projectRepositoryMock;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. Mock للخدمة الأساسية
    $this->analyticsServiceMock = $this->mock(AnalyticsService::class);

    // 2. Mock للـ Repository باستخدام الواجهة لأن الـ DTO يطلبها
    $this->projectRepositoryMock = $this->mock(ProjectRepositoryInterface::class);
    $this->app->instance(ProjectRepositoryInterface::class, $this->projectRepositoryMock);
  }

  /**
   * projectOverview now type-hints AnalyticsFilterRequest so the date/limit
   * filters are validated before they reach the SQL and the cache key. Calling
   * the controller directly therefore has to hand it that request type.
   */
  private function createAnalyticsFilterRequest(array $params = []): AnalyticsFilterRequest
  {
    $base = $this->createAnalyticsRequest($params);

    $request = AnalyticsFilterRequest::createFrom($base);
    $request->setContainer($this->app);

    $this->app->instance('request', $request);

    return $request;
  }

  private function createAnalyticsRequest(array $params = []): Request
  {
    $mockProject = new \App\Models\Project();
    $mockProject->id = 1;
    $mockProject->public_id = 'proj_test_1';
    $mockProject->name = 'Test Project';
    $mockProject->enabled_modules = [];

    $this->app->instance('currentProject', $mockProject);

    $this->projectRepositoryMock
      ->shouldReceive('findByKey')
      ->zeroOrMoreTimes()
      ->andReturn($mockProject);

    $data = array_merge(['project' => 'proj_test_1', 'from' => '2026-01-01', 'to' => '2026-05-01'], $params);

    // إضافة Bearer Token افتراضي للطلبات لتجنب أخطاء النوع (TypeError)
    $request = Request::create(
      '/api/cms/analytics/dummy',
      'GET',
      $data,
      [],
      [],
      ['HTTP_AUTHORIZATION' => 'Bearer fake-jwt-token']
    );

    $this->app->instance('request', $request);

    return $request;
  }

  #[Test]
  public function it_can_fetch_admin_overview()
  {
    $this->analyticsServiceMock->shouldReceive('adminOverview')->once()->andReturn(['platform_data' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('projectsGrowth')->once()->andReturn(['growth_data' => 'ok']);

    $request = $this->createAnalyticsRequest();
    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    $response = $controller->adminOverview($request);
    $this->assertEquals(200, $response->getStatusCode());

    $responseData = $response->getData();
    $this->assertTrue($responseData->success);
    $this->assertNotNull($responseData->data);
  }

  #[Test]
  public function it_can_fetch_project_overview()
  {
    $this->analyticsServiceMock->shouldReceive('contentSummary')->once()->andReturn(['summary' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('contentGrowth')->once()->andReturn(['growth' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('topRatedEntries')->once()->andReturn(['top' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('ratingsReport')->once()->andReturn(['report' => 'ok']);

    $request = $this->createAnalyticsFilterRequest();
    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    $ecommerceMock = \Mockery::mock(\App\Services\EcommerceAnalyticsClient::class);
    $bookingMock = \Mockery::mock(\App\Services\BookingAnalyticsClient::class);

    $response = $controller->projectOverview($request, $ecommerceMock, $bookingMock);

    $this->assertEquals(200, $response->getStatusCode());

    $responseData = $response->getData();
    $this->assertTrue($responseData->success);
    $this->assertObjectHasProperty('data', $responseData);

    // No module enabled, so nothing to fail.
    $this->assertObjectNotHasProperty('partial_failures', $responseData);
  }

  #[Test]
  public function it_can_fetch_project_overview_with_modules()
  {
    $this->analyticsServiceMock->shouldReceive('contentSummary')->once()->andReturn(['summary' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('contentGrowth')->once()->andReturn(['growth' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('topRatedEntries')->once()->andReturn(['top' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('ratingsReport')->once()->andReturn(['report' => 'ok']);

    $request = $this->createAnalyticsFilterRequest();
    $mockProject = app('currentProject');
    $mockProject->enabled_modules = ['ecommerce', 'booking'];

    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    // The clients now describe the call instead of performing it; the
    // controller issues both through one Http::pool.
    $ecommerceMock = \Mockery::mock(\App\Services\EcommerceAnalyticsClient::class);
    $ecommerceMock->shouldReceive('summaryRequest')->once()->andReturn([
      'url' => 'http://ecommerce.test/api/ecommerce/analytics/summary',
      'headers' => [],
      'query' => [],
    ]);

    $bookingMock = \Mockery::mock(\App\Services\BookingAnalyticsClient::class);
    $bookingMock->shouldReceive('overviewRequest')->once()->andReturn([
      'url' => 'http://booking.test/api/booking/analytics/overview',
      'headers' => [],
      'query' => [],
    ]);

    Http::fake([
      'ecommerce.test/*' => Http::response(['data' => ['ecommerce_data' => 'ok']], 200),
      // Booking is down: the module must fail on its own without taking
      // ecommerce — or the whole report — with it.
      'booking.test/*' => Http::response(['message' => 'Booking service down'], 503),
    ]);

    $response = $controller->projectOverview($request, $ecommerceMock, $bookingMock);

    $this->assertEquals(200, $response->getStatusCode());

    $responseData = $response->getData();

    $this->assertEquals('ok', $responseData->data->ecommerce->ecommerce_data);
    $this->assertNull($responseData->data->booking);

    // The failure is reported rather than being indistinguishable from "no data".
    $this->assertObjectHasProperty('partial_failures', $responseData);
    $this->assertObjectHasProperty('booking', $responseData->partial_failures);
    $this->assertStringContainsString('503', $responseData->partial_failures->booking);
  }

  #[Test]
  public function it_handles_ecommerce_service_exception_gracefully()
  {
    $this->analyticsServiceMock->shouldReceive('contentSummary')->once()->andReturn(['summary' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('contentGrowth')->once()->andReturn(['growth' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('topRatedEntries')->once()->andReturn(['top' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('ratingsReport')->once()->andReturn(['report' => 'ok']);

    $request = $this->createAnalyticsFilterRequest();
    $mockProject = app('currentProject');
    $mockProject->enabled_modules = ['ecommerce']; // تفعيل الموديول ليدخل إلى الشرط

    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    $ecommerceMock = \Mockery::mock(\App\Services\EcommerceAnalyticsClient::class);
    $ecommerceMock->shouldReceive('summaryRequest')->once()->andReturn([
      'url' => 'http://ecommerce.test/api/ecommerce/analytics/summary',
      'headers' => [],
      'query' => [],
    ]);

    $bookingMock = \Mockery::mock(\App\Services\BookingAnalyticsClient::class);

    Http::fake([
      'ecommerce.test/*' => Http::response(['message' => 'Ecommerce service down'], 500),
    ]);

    $response = $controller->projectOverview($request, $ecommerceMock, $bookingMock);

    $this->assertEquals(200, $response->getStatusCode());

    // الفشل لا يوقف التقرير، لكنه يُبلَّغ عنه صراحةً
    $responseData = $response->getData();
    $this->assertTrue($responseData->success);
    $this->assertNull($responseData->data->ecommerce);
    $this->assertObjectHasProperty('ecommerce', $responseData->partial_failures);
  }
}
