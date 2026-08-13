<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\CMS\Services\AnalyticsService;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Http\Controllers\CmsAnalyticsController;
use Mockery\MockInterface;
use Illuminate\Http\Request;
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

    $request = $this->createAnalyticsRequest();
    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    $ecommerceMock = \Mockery::mock(\App\Services\EcommerceAnalyticsClient::class);
    $bookingMock = \Mockery::mock(\App\Services\BookingAnalyticsClient::class);

    $response = $controller->projectOverview($request, $ecommerceMock, $bookingMock);

    $this->assertEquals(200, $response->getStatusCode());

    $responseData = $response->getData();
    $this->assertTrue($responseData->success);
    $this->assertObjectHasProperty('data', $responseData);
  }

  #[Test]
  public function it_can_fetch_project_overview_with_modules()
  {
    $this->analyticsServiceMock->shouldReceive('contentSummary')->once()->andReturn(['summary' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('contentGrowth')->once()->andReturn(['growth' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('topRatedEntries')->once()->andReturn(['top' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('ratingsReport')->once()->andReturn(['report' => 'ok']);

    $request = $this->createAnalyticsRequest();
    $mockProject = app('currentProject');
    $mockProject->enabled_modules = ['ecommerce', 'booking'];

    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    $ecommerceMock = \Mockery::mock(\App\Services\EcommerceAnalyticsClient::class);
    $ecommerceMock->shouldReceive('getSummary')->once()->andReturn(['ecommerce_data' => 'ok']);

    $bookingMock = \Mockery::mock(\App\Services\BookingAnalyticsClient::class);
    $bookingMock->shouldReceive('getOverview')->once()->andThrow(new \Exception('Booking service down'));

    $response = $controller->projectOverview($request, $ecommerceMock, $bookingMock);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_handles_ecommerce_service_exception_gracefully()
  {
    $this->analyticsServiceMock->shouldReceive('contentSummary')->once()->andReturn(['summary' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('contentGrowth')->once()->andReturn(['growth' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('topRatedEntries')->once()->andReturn(['top' => 'ok']);
    $this->analyticsServiceMock->shouldReceive('ratingsReport')->once()->andReturn(['report' => 'ok']);

    $request = $this->createAnalyticsRequest();
    $mockProject = app('currentProject');
    $mockProject->enabled_modules = ['ecommerce']; // تفعيل الموديول ليدخل إلى الشرط

    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    // جعل الـ Client يرمي Exception لتغطية كود الـ catch
    $ecommerceMock = \Mockery::mock(\App\Services\EcommerceAnalyticsClient::class);
    $ecommerceMock->shouldReceive('getSummary')->once()->andThrow(new \Exception('Ecommerce service down'));

    $bookingMock = \Mockery::mock(\App\Services\BookingAnalyticsClient::class);

    $response = $controller->projectOverview($request, $ecommerceMock, $bookingMock);

    $this->assertEquals(200, $response->getStatusCode());

    // التأكد أن الـ catch قامت بتعيين القيمة إلى null وعدم إيقاف التنفيذ
    $responseData = $response->getData();
    $this->assertTrue($responseData->success);
    $this->assertNull($responseData->data->ecommerce);
  }
}
