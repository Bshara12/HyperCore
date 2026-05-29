<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\DataTypeEntriesController;
use App\Domains\CMS\Read\Services\EntryReadService;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class DataTypeEntriesControllerTest extends TestCase
{
  private DataTypeEntriesController $controller;
  private $entryReadServiceMock;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. محاكاة الـ Service الوحيدة المحقونة في الباني (Constructor)
    $this->entryReadServiceMock = Mockery::mock(EntryReadService::class);

    // 2. إنشاء كائن الـ Controller وتمرير الـ Mock له مباشرة
    $this->controller = new DataTypeEntriesController($this->entryReadServiceMock);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function it_can_get_entries_by_data_type_slug_with_all_filters()
  {
    // 1. تجهيز المدخلات الوهمية للرابط (Route Parameters)
    $projectId = 55;
    $slug = 'blog-posts';

    // 2. محاكاة الـ Request والـ Query Parameters المتوقع قراءتها
    $requestMock = Mockery::mock(Request::class);

    // استخدام أسلوب المرونة الكاملة لمحاكاة دالة query() مع القيم الافتراضية
    $requestMock->shouldReceive('query')->andReturnUsing(function ($key, $default = null) {
      return match ($key) {
        'lang' => 'ar',
        'page' => 2,
        'per_page' => 15,
        'search' => 'laravel',
        'field_id' => '12',
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-29',
        default => $default,
      };
    });

    // 3. البيانات المتوقع إرجاعها من الـ Service
    $mockEntriesResponse = [
      'current_page' => 2,
      'data' => [
        ['id' => 101, 'title' => 'First Post'],
        ['id' => 102, 'title' => 'Second Post']
      ],
      'total' => 2
    ];

    // 4. بناء التوقعات للـ Service للتأكد من استلام الفلاتر كاملة ومطابقة
    $this->entryReadServiceMock->shouldReceive('getEntriesByDataTypeSlug')
      ->once()
      ->with(
        $projectId,
        $slug,
        [
          'lang' => 'ar',
          'page' => 2,
          'per_page' => 15,
          'search' => 'laravel',
          'field_id' => '12',
          'date_from' => '2026-05-01',
          'date_to' => '2026-05-29',
        ]
      )
      ->andReturn($mockEntriesResponse);

    // 5. التنفيذ الفعلي باستدعاء تابع index مباشرة
    $response = $this->controller->index($requestMock, $projectId, $slug);

    // 6. التأكيدات (Assertions)
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(json_encode($mockEntriesResponse), $response->getContent());
  }
}
