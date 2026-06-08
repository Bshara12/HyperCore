<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\EntryDetailController;
use App\Domains\CMS\Read\Services\EntryReadService;
use App\Domains\CMS\Read\DTOs\GetEntryDetailDTO;
use App\Models\DataEntry;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class EntryDetailControllerTest extends TestCase
{
  private EntryDetailController $controller;
  private $entryReadServiceMock;

  protected function setUp(): void
  {
    parent::setUp();

    $this->entryReadServiceMock = Mockery::mock(EntryReadService::class);
    $this->controller = new EntryDetailController($this->entryReadServiceMock);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  // ==========================================
  // 1. اختبارات دالة SHOW
  // ==========================================
  #[Test]
  public function it_can_show_entry_details_successfully()
  {
    $entry = new DataEntry();
    $entry->id = 7;

    $request = Request::create('/entry', 'GET', ['lang' => 'ar']);
    $request->attributes->set('auth_user', ['id' => 99]);

    $mockDetail = ['id' => 7, 'title' => 'عنوان المقال', 'lang' => 'ar'];

    $this->entryReadServiceMock->shouldReceive('getDetail')
      ->once()
      ->with(Mockery::type(GetEntryDetailDTO::class))
      ->andReturn($mockDetail);

    $response = $this->controller->show($request, $entry);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(json_encode($mockDetail), $response->getContent());
  }

  #[Test]
  public function it_returns_404_if_entry_detail_not_found()
  {
    $entry = new DataEntry();
    $entry->id = 7;
    $request = Request::create('/entry', 'GET');

    $this->entryReadServiceMock->shouldReceive('getDetail')->once()->andReturn(null);

    $response = $this->controller->show($request, $entry);

    $this->assertEquals(404, $response->getStatusCode());
  }

  // ==========================================
  // 2. اختبارات دالة SHOW WITH RELATION
  // ==========================================
  #[Test]
  public function it_can_show_entry_with_relations_successfully()
  {
    $entry = new DataEntry();
    $entry->id = 15;
    $request = Request::create('/entry-relations', 'GET', ['lang' => 'en']);

    $mockData = ['id' => 15, 'relations' => ['comments' => []]];
    $this->entryReadServiceMock->shouldReceive('getWithRelations')
      ->once()
      ->with(15, 'en')
      ->andReturn($mockData);

    $response = $this->controller->showwithrelation($request, $entry);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_404_if_entry_with_relations_not_found()
  {
    $entry = new DataEntry();
    $entry->id = 15;
    $request = Request::create('/entry-relations', 'GET');

    $this->entryReadServiceMock->shouldReceive('getWithRelations')->once()->andReturn(null);

    $response = $this->controller->showwithrelation($request, $entry);

    $this->assertEquals(404, $response->getStatusCode());
  }

  // ==========================================
  // 3. اختبارات دالة SHOW WITH SAME TYPE
  // ==========================================
  #[Test]
  public function it_can_show_entries_with_same_type_filtered()
  {
    $entry = new DataEntry();
    $entry->id = 22;

    $request = Request::create('/same-type-filtered', 'GET', [
      'lang' => 'ar',
      'page' => '3',
      'per_page' => '10',
      'all' => '1',
      'date_from' => '2026-01-01',
      'date_to' => '2026-05-29',
      'field_id' => '5',
      'search' => 'PHP'
    ]);

    $mockResult = ['data' => [], 'total' => 0];

    $this->entryReadServiceMock->shouldReceive('getSameTypeFiltered')
      ->once()
      ->with(22, 'ar', '2026-01-01', '2026-05-29', 5, 'PHP', true, 3, 10)
      ->andReturn($mockResult);

    $response = $this->controller->showwithsametype($request, $entry);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_404_if_show_with_same_type_returns_null()
  {
    $entry = new DataEntry();
    $entry->id = 22;
    $request = Request::create('/same-type-filtered', 'GET');

    $this->entryReadServiceMock->shouldReceive('getSameTypeFiltered')->andReturn(null);

    $response = $this->controller->showwithsametype($request, $entry);

    $this->assertEquals(404, $response->getStatusCode());
  }

  // ==========================================
  // 4. اختبار دالة SAME TYPE (المعدل والمصلح)
  // ==========================================
  #[Test]
  public function it_calls_same_type_filtered_via_input_parameters()
  {
    // 🌟 تعديل: استخدام Request::create لضمان قراءة المدخلات عبر الـ input()
    $request = Request::create('/same-type', 'POST', [
      'lang' => 'en',
      'page' => 1,
      'per_page' => 20
    ]);

    // 🌟 تعديل: تمرير الوسائط بالترتيب المكتمل الذي يراه الـ PHP (يحتوي على القيمة الافتراضية للـ all وهي false في الموقع السابع)
    $this->entryReadServiceMock->shouldReceive('getSameTypeFiltered')
      ->once()
      ->with(50, 'en', null, null, null, null, false, 1, 20)
      ->andReturn(['status' => 'success']);

    $response = $this->controller->sameType($request, 50);

    $this->assertIsArray($response);
  }

  // ==========================================
  // 5. اختبار دالة SHOW MANY (المعدل والمصلح)
  // ==========================================
  #[Test]
  public function it_can_show_many_entries_by_ids()
  {
    // 🌟 تعديل: تهيئة الـ Request بـ POST payload حقيقي لكي تعمل خاصية الـ $request->ids الديناميكية بنجاح
    $request = Request::create('/show-many', 'POST', ['ids' => [1, 2, 3]]);

    $this->entryReadServiceMock->shouldReceive('showMany')
      ->once()
      ->with([1, 2, 3])
      ->andReturn([['id' => 1], ['id' => 2], ['id' => 3]]);

    $response = $this->controller->showMany($request);

    $this->assertCount(3, $response);
  }
}
