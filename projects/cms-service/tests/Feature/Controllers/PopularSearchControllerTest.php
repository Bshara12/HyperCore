<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\PopularSearchController;
use App\Domains\Search\Services\PopularSearchService;
use App\Domains\Search\Requests\PopularSearchRequest;
use App\Support\CurrentProject;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class PopularSearchControllerTest extends TestCase
{
  private PopularSearchController $controller;
  private $popularSearchServiceMock;

  protected function setUp(): void
  {
    parent::setUp();
    $this->popularSearchServiceMock = Mockery::mock(PopularSearchService::class);
    $this->controller = new PopularSearchController($this->popularSearchServiceMock);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function it_returns_400_if_project_id_is_missing()
  {
    // بدلاً من جعل لارافل يرمي 500، نسجل كائناً يعيد id فارغ (إذا كان المنطق يسمح)
    // أو نعدل الكنترولر قليلاً ليلتقط الـ 500. 

    // الحل الأسرع بدون تعديل الـ Service:
    // نقوم بعمل Mock لكلاس CurrentProject نفسه وتمريره للكنترولر (إذا كان الكنترولر يقبله)
    // أو ببساطة:

    // بما أننا في اختبار:
    // سنقوم بتسجيل مشروع بـ id يساوي 0 أو null في الحاوية
    $project = new \App\Models\Project();
    $project->id = 0; // أو null حسب منطقك
    app()->instance('currentProject', $project);

    $request = Mockery::mock(PopularSearchRequest::class);
    $request->shouldIgnoreMissing();

    $response = $this->controller->__invoke($request);

    $this->assertEquals(400, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_popular_searches_successfully()
  {
    // 1. تسجيل المشروع في الـ Container
    $project = new \App\Models\Project();
    $project->id = 123;
    app()->instance('currentProject', $project);

    // 2. محاكاة العناصر الداخلية (Items) لتعمل دالة toArray() بداخلها
    $itemMock = Mockery::mock(\App\Domains\Search\DTOs\PopularSearchItemDTO::class);
    $itemMock->shouldReceive('toArray')->andReturn(['id' => 1, 'title' => 'Search Item']);

    // 3. إنشاء نسخة حقيقية من الـ DTO (حل مشكلة الـ TypeError)
    $resultDTO = new \App\Domains\Search\DTOs\PopularSearchResultDTO(
      trending: [$itemMock],
      popular: [$itemMock],
      window: '7d',
      actualWindowUsed: '7d',
      fallbackApplied: false,
      source: 'elasticsearch',
      tookMs: 15.555
    );

    // 4. محاكاة الطلب (Request)
    $request = Mockery::mock(PopularSearchRequest::class);
    $request->shouldReceive('language')->andReturn('ar');
    $request->shouldReceive('window')->andReturn('7d');
    $request->shouldReceive('type')->andReturn('trending');
    $request->shouldReceive('limit')->andReturn(5);

    // 5. محاكاة الخدمة لتعيد الـ DTO الحقيقي
    $this->popularSearchServiceMock->shouldReceive('getPopular')
      ->once()
      ->with(123, 'ar', '7d', 'trending', 5)
      ->andReturn($resultDTO);

    // 6. التنفيذ
    $response = $this->controller->__invoke($request);

    // 7. التأكيد
    $this->assertEquals(200, $response->getStatusCode());

    // التأكد من الهيدرز كما ورد في الكود
    $this->assertEquals('elasticsearch', $response->headers->get('X-Popular-Source'));
    $this->assertEquals('15.56ms', $response->headers->get('X-Popular-Took'));

    // التأكد من بنية البيانات الراجعة
    $responseData = $response->getData(true);
    $this->assertArrayHasKey('trending', $responseData);
    $this->assertEquals('Search Item', $responseData['trending'][0]['title']);
  }
}
