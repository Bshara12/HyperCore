<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\EntryVersionController;
use App\Domains\CMS\Read\Services\EntryVersionReadService;
use App\Domains\CMS\Read\Requests\EntryVersionsRequest;
use App\Domains\CMS\Read\DTOs\EntryVersionsListDTO; // 🌟 استدعاء الـ DTO لمنع الـ TypeError
use App\Models\Project;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class EntryVersionControllerTest extends TestCase
{
  private EntryVersionController $controller;
  private $entryVersionReadServiceMock;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. محاكاة الـ Service الخاصة بقراءة إصدارات العناصر
    $this->entryVersionReadServiceMock = Mockery::mock(EntryVersionReadService::class);

    // 2. تلبية احتياج كلاس CurrentProject عبر حاوية لارافيل الحقيقية
    $fakeProject = new Project();
    $fakeProject->id = 99;
    $this->app->instance('currentProject', $fakeProject);

    $this->controller = new EntryVersionController($this->entryVersionReadServiceMock);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  // ==========================================
  // 1. اختبار حالة استدعاء الدالة بالقيم الافتراضية
  // ==========================================
  #[Test]
  public function it_can_list_entry_versions_with_default_pagination_values()
  {
    $entrySlug = 'about-us-page';
    $request = EntryVersionsRequest::create('/versions', 'GET');

    // 🌟 الحل: عمل Mock لكائن الـ DTO لإرضاء توقيع الدالة الصارم في PHP
    $mockDto = Mockery::mock(EntryVersionsListDTO::class);

    $this->entryVersionReadServiceMock->shouldReceive('listForEntrySlug')
      ->once()
      ->with(
        99,              // projectId
        $entrySlug,      // entrySlug
        1,               // page الافتراضي
        20,              // perPage الافتراضي
        false            // withSnapshot الافتراضي
      )
      ->andReturn($mockDto); // إرجاع كائن الـ DTO الحقيقي/الوهمي المتوافق مع الـ Type Hint

    $response = $this->controller->index($request, $entrySlug);

    // التأكد من نجاح العملية ووصول الكنترولر لنهاية الدالة بنجاح
    $this->assertEquals(200, $response->getStatusCode());
  }

  // ==========================================
  // 2. اختبار حالة تمرير قيم مخصصة في الـ Query String
  // ==========================================
  #[Test]
  public function it_can_list_entry_versions_with_custom_query_parameters()
  {
    $entrySlug = 'contact-info';

    $request = EntryVersionsRequest::create('/versions', 'GET', [
      'page' => '5',
      'per_page' => '50',
      'with_snapshot' => '1'
    ]);

    // 🌟 عمل Mock لكائن الـ DTO لإرضاء توقيع الدالة الصارم في PHP
    $mockDto = Mockery::mock(EntryVersionsListDTO::class);

    $this->entryVersionReadServiceMock->shouldReceive('listForEntrySlug')
      ->once()
      ->with(
        99,             // projectId
        $entrySlug,     // entrySlug
        5,              // page المخصصة كـ int
        50,             // perPage المخصصة كـ int
        true            // withSnapshot المخصصة كـ bool
      )
      ->andReturn($mockDto);

    $response = $this->controller->index($request, $entrySlug);

    $this->assertEquals(200, $response->getStatusCode());
  }
}
