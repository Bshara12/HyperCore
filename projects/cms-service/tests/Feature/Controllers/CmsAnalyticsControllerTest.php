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

    // 2. Mock للـ Repository باستخدام الواجهة (Interface) لأن الـ DTO يطلبها
    $this->projectRepositoryMock = $this->mock(ProjectRepositoryInterface::class);
    $this->app->instance(ProjectRepositoryInterface::class, $this->projectRepositoryMock);
  }

  private function createAnalyticsRequest(array $params = []): Request
  {
    // 1. إنشاء نسخة حقيقية من الموديل بدلاً من stdClass
    $mockProject = new \App\Models\Project();
    $mockProject->id = 1;
    $mockProject->public_id = 'proj_test_1';
    $mockProject->name = 'Test Project';

    // حقن المشروع في الـ Container
    $this->app->instance('currentProject', $mockProject);

    // إجبار الـ Repository على إرجاع الموديل الحقيقي
    $this->projectRepositoryMock
      ->shouldReceive('findByKey')
      ->zeroOrMoreTimes()
      ->andReturn($mockProject); // الآن سنعيد الموديل الذي يتوقعه الـ DTO

    $data = array_merge(['project' => 'proj_test_1', 'from' => '2026-01-01', 'to' => '2026-05-01'], $params);
    $request = Request::create('/api/cms/analytics/dummy', 'GET', $data);

    $this->app->instance('request', $request);

    return $request;
  }

  #[Test]
  public function it_can_fetch_admin_overview()
  {
    $this->analyticsServiceMock->shouldReceive('adminOverview')->once()->andReturn(['data' => 'ok']);

    $request = $this->createAnalyticsRequest();
    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    $response = $controller->adminOverview($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_fetch_projects_growth()
  {
    // 1. تعريف البيانات الوهمية التي ستعيدها الـ Service
    $mockData = [
      'labels' => ['Jan', 'Feb', 'Mar'],
      'values' => [10, 25, 40]
    ];

    // 2. توقع استدعاء الـ Service وتمرير الـ DTO
    $this->analyticsServiceMock
      ->shouldReceive('projectsGrowth')
      ->once()
      ->andReturn($mockData);

    // 3. استدعاء الطلب (سيقوم بحل الـ DTO تلقائياً)
    $request = $this->createAnalyticsRequest();
    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    // 4. تنفيذ التابع
    $response = $controller->projectsGrowth($request);

    // 5. التحقق من النتيجة
    $this->assertEquals(200, $response->getStatusCode());

    // التحقق من أن الاستجابة تحتوي على success و data بشكل صحيح
    $responseData = $response->getData();
    $this->assertTrue($responseData->success);
    $this->assertEquals($mockData, (array)$responseData->data);
  }

  #[Test]
  public function it_can_fetch_content_summary()
  {
    $this->analyticsServiceMock->shouldReceive('contentSummary')->once()->andReturn(['data' => 'ok']);

    $request = $this->createAnalyticsRequest();
    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    $response = $controller->contentSummary($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_fetch_content_growth()
  {
    $this->analyticsServiceMock->shouldReceive('contentGrowth')->once()->andReturn(['data' => 'ok']);

    $request = $this->createAnalyticsRequest();
    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    $response = $controller->contentGrowth($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_fetch_top_rated()
  {
    $this->analyticsServiceMock->shouldReceive('topRatedEntries')->once()->andReturn(['data' => 'ok']);

    $request = $this->createAnalyticsRequest();
    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    $response = $controller->topRated($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_fetch_ratings_report()
  {
    $this->analyticsServiceMock->shouldReceive('ratingsReport')->once()->andReturn(['data' => 'ok']);

    $request = $this->createAnalyticsRequest();
    $controller = new CmsAnalyticsController($this->analyticsServiceMock);

    $response = $controller->ratingsReport($request);
    $this->assertEquals(200, $response->getStatusCode());
  }
}
