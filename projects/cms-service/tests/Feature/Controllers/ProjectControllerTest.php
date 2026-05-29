<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\ProjectController;
use App\Domains\CMS\Services\ProjectService;
use App\Domains\CMS\Requests\CreateProjectRequest;
use App\Domains\CMS\Requests\UpdateProjectRequest;
use App\Models\Project;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\ParameterBag;

class ProjectControllerTest extends TestCase
{
  private $projectServiceMock;

  protected function setUp(): void
  {
    parent::setUp();
    $this->projectServiceMock = Mockery::mock(ProjectService::class);
    $this->app->instance(ProjectService::class, $this->projectServiceMock);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function it_stores_a_new_project()
  {
    // 1. إعداد البيانات الوهمية
    $data = [
      'name' => 'New Project',
      'supported_languages' => ['en', 'ar'],
      'enabled_modules' => ['cms']
    ];

    // 2. إعداد الموك مع توقع لـ all() و attributes
    $request = Mockery::mock(CreateProjectRequest::class);
    $request->attributes = new \Symfony\Component\HttpFoundation\ParameterBag([
      'auth_user' => ['id' => 1]
    ]);

    // هنا الحل: إخبار الموك أن يعيد البيانات عند استدعاء all()
    $request->shouldReceive('all')->andReturn($data);
    // ويفضل أيضاً إخبار الموك أن يعيد القيم عند استدعاء input إذا كان الـ DTO يستخدمه
    $request->shouldReceive('input')->andReturnUsing(fn($key) => $data[$key] ?? null);

    // توقع استدعاء الخدمة
    $this->projectServiceMock->shouldReceive('create')->once()->andReturn(new Project(['id' => 1]));

    $controller = new ProjectController($this->projectServiceMock);
    $response = $controller->store($request, $this->projectServiceMock);

    $this->assertEquals(201, $response->getStatusCode());
  }

  #[Test]
  public function it_updates_a_project()
  {
    $project = new Project(['id' => 1]);

    // 2. حل مشكلة input: تحديد ما يجب أن يعيده الـ Mock عند استدعاء input
    $request = Mockery::mock(UpdateProjectRequest::class);
    $request->shouldReceive('input')->with('name')->andReturn('Updated Name');
    $request->shouldReceive('input')->with('supported_languages')->andReturn(['en']);
    $request->shouldReceive('input')->with('enabled_modules')->andReturn(['cms']);

    $this->projectServiceMock->shouldReceive('update')->once()->andReturn($project);

    $controller = new ProjectController($this->projectServiceMock);
    $response = $controller->update($request, $project, $this->projectServiceMock);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_lists_projects()
  {
    // 3. حل مشكلة Collection: استخدام collect() بدلاً من array
    $this->projectServiceMock->shouldReceive('list')->once()->andReturn(collect([new Project()]));

    $controller = new ProjectController($this->projectServiceMock);
    $response = $controller->index($this->projectServiceMock);

    $this->assertEquals(200, $response->getStatusCode());
    // التأكد من أنه Collection
    $this->assertIsArray($response->getData(true));
  }

  #[Test]
  public function it_deletes_a_project()
  {
    $project = new Project(['id' => 1]);
    $this->projectServiceMock->shouldReceive('delete')->once()->with($project);

    $controller = new ProjectController($this->projectServiceMock);
    $response = $controller->destroy($project, $this->projectServiceMock);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_resolves_a_project()
  {
    // 1. محاكاة الطلب (Request)
    $request = Mockery::mock(\Illuminate\Http\Request::class);

    // 2. محاكاة الخدمة لتعيد بيانات المشروع
    $this->projectServiceMock->shouldReceive('resolve')
      ->once()
      ->with($request)
      ->andReturn(['id' => 1, 'name' => 'Resolved Project']);

    $controller = new ProjectController($this->projectServiceMock);
    $response = $controller->resolve($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Resolved Project', $response->getData(true)['name']);
  }

  #[Test]
  public function it_shows_a_project()
  {
    // 1. إنشاء موديل حقيقي (هذا هو الكائن الذي تتوقعه الخدمة)
    $project = new Project(['id' => 1]);

    // 2. إنشاء موديل آخر ليمثل النتيجة المرجعة (التي ستظهر في الـ JSON)
    $resultProject = new Project(['id' => 1, 'name' => 'Project Details']);

    // 3. محاكاة الخدمة لتعيد كائن Project (وليس array)
    $this->projectServiceMock->shouldReceive('show')
      ->once()
      ->with($project)
      ->andReturn($resultProject);

    $controller = new ProjectController($this->projectServiceMock);
    $response = $controller->show($project, $this->projectServiceMock);

    $this->assertEquals(200, $response->getStatusCode());

    // التأكد من البيانات
    $this->assertEquals('Project Details', $response->getData(true)['name']);
  }
}
