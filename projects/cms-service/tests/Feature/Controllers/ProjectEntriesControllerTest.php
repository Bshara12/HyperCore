<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\ProjectEntriesController;
use App\Domains\CMS\Read\Services\EntryReadService;
use App\Domains\CMS\Requests\ProjectEntriesRequest;
use App\Models\Project;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ProjectEntriesControllerTest extends TestCase
{
  private $entryReadServiceMock;

  protected function setUp(): void
  {
    parent::setUp();
    $this->entryReadServiceMock = Mockery::mock(EntryReadService::class);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function it_returns_project_entries_successfully()
  {
    // 1. إعداد الـ Mock الخاص بالـ Request
    $request = Mockery::mock(ProjectEntriesRequest::class);

    // تعليم الـ Mock أن يعيد مصفوفة فلترات وهمية عند استدعاء getFilters
    $expectedFilters = [
      'lang' => 'ar',
      'page' => 1,
      'per_page' => 20
    ];
    $request->shouldReceive('getFilters')->once()->andReturn($expectedFilters);

    // 2. توقع استدعاء الخدمة بالمعاملات الصحيحة
    // index() يستقبل الآن موديل Project (Route Binding) بدلاً من int
    $projectId = 123;
    $project = (new Project())->forceFill(['id' => $projectId]);
    $this->entryReadServiceMock->shouldReceive('getProjectEntriesTree')
      ->once()
      ->with($projectId, $expectedFilters)
      ->andReturn(['tree' => 'data']);

    // 3. إنشاء الكنترولر وتمرير الخدمة (Constructor Injection)
    $controller = new ProjectEntriesController($this->entryReadServiceMock);

    // 4. تنفيذ الطلب
    $response = $controller->index($request, $project);

    // 5. التأكيد
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(['tree' => 'data'], $response->getData(true));
  }
}
