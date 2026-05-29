<?php

use App\Domains\CMS\Actions\Project\CreateProjectAction;
use App\Domains\CMS\Actions\Project\DeleteProjectAction;
use App\Domains\CMS\Actions\Project\ListProjectsAction;
use App\Domains\CMS\Actions\Project\ShowProjectAction;
use App\Domains\CMS\Actions\Project\UpdateProjectAction;
use App\Domains\CMS\DTOs\CreateProjectDTO;
use App\Domains\CMS\DTOs\project\UpdateProjectDTO;
use App\Domains\CMS\Services\ProjectService;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

beforeEach(function () {
  $this->createAction = Mockery::mock(CreateProjectAction::class);
  $this->updateAction = Mockery::mock(UpdateProjectAction::class);
  $this->showAction = Mockery::mock(ShowProjectAction::class);
  $this->listAction = Mockery::mock(ListProjectsAction::class);
  $this->deleteAction = Mockery::mock(DeleteProjectAction::class);
  $this->projectModel = Mockery::mock(Project::class);

  $this->service = new ProjectService(
    $this->createAction,
    $this->updateAction,
    $this->showAction,
    $this->listAction,
    $this->deleteAction,
    $this->projectModel
  );
});

afterEach(function () {
  Mockery::close();
});

// اختبارات الـ Delegation المباشرة
test('it calls create action', function () {
  $dto = Mockery::mock(CreateProjectDTO::class);
  $project = new Project();
  $this->createAction->shouldReceive('execute')->once()->with($dto)->andReturn($project);

  $result = $this->service->create($dto);
  expect($result)->toBe($project);
});

test('it calls update action', function () {
  $project = new Project();
  $dto = Mockery::mock(UpdateProjectDTO::class);
  $this->updateAction->shouldReceive('execute')->once()->with($project, $dto)->andReturn($project);

  $result = $this->service->update($project, $dto);
  expect($result)->toBe($project);
});

test('it lists all projects', function () {
  $collection = new Collection([new Project()]);
  $this->listAction->shouldReceive('execute')->once()->andReturn($collection);

  $result = $this->service->list();
  expect($result)->toBe($collection);
});

// اختبار تابع resolve (المنطق المباشر)
test('resolve returns 400 when project header is missing', function () {
  $request = Mockery::mock(Request::class);
  $request->shouldReceive('header')->with('X-Project-Id')->andReturn(null);

  $result = $this->service->resolve($request);

  expect($result)->toBeInstanceOf(JsonResponse::class)
    ->and($result->getStatusCode())->toBe(400);
});

test('resolve returns 404 when project not found', function () {
  $request = Mockery::mock(Request::class);
  $request->shouldReceive('header')->with('X-Project-Id')->andReturn('invalid-id');

  // Mock خاص لهذا الاختبار
  $projectMock = Mockery::mock(Project::class);
  $projectMock->shouldReceive('where->first')->once()->andReturn(null);

  // إنشاء الخدمة بـ 6 معتمديات
  $service = new ProjectService(
    $this->createAction,
    $this->updateAction,
    $this->showAction,
    $this->listAction,
    $this->deleteAction,
    $projectMock // المعتمدية السادسة
  );

  $result = $service->resolve($request);

  expect($result->getStatusCode())->toBe(404);
});

test('resolve returns json project data when project is found', function () {
  // 1. Arrange: تهيئة البيانات
  $projectKey = 'valid-project-id';
  $request = Mockery::mock(Request::class);
  $request->shouldReceive('header')->with('X-Project-Id')->andReturn($projectKey);

  // إنشاء كائن مشروع حقيقي (بدون قاعدة بيانات) لضمان دقة البيانات
  $project = new Project([
    'public_id' => $projectKey,
    'name' => 'My Test Project',
    'owner_id' => 5,
    'enabled_modules' => ['cms', 'ecommerce']
  ]);

  $project->id = 10;

  // جعل الـ Mock الخاص بـ projectModel يعيد هذا المشروع عند البحث
  $this->projectModel->shouldReceive('where->first')->once()->andReturn($project);

  // 2. Act: تنفيذ التابع
  $result = $this->service->resolve($request);

  expect($result->getStatusCode())->toBe(200);

  // الحصول على البيانات داخل الـ JSON للتأكد من مطابقتها
  $data = $result->getData(true);

  expect($data)->toBe([
    'id' => 10,
    'public_id' => 'valid-project-id',
    'name' => 'My Test Project',
    'owner_id' => 5,
    'enabled_modules' => ['cms', 'ecommerce']
  ]);
});

test('it calls show action', function () {
  // 1. Arrange: إنشاء كائن Project وهمي
  $project = new Project();

  // 2. Expectation: التأكد من أن الـ action يستقبل المشروع ويعيده
  $this->showAction->shouldReceive('execute')
    ->once()
    ->with($project)
    ->andReturn($project);

  // 3. Act & Assert: تنفيذ الاختبار والتحقق
  $result = $this->service->show($project);
  expect($result)->toBe($project);
});

test('it calls delete action', function () {
  // 1. Arrange: إنشاء كائن Project وهمي
  $project = new Project();

  // 2. Expectation: التأكد من استدعاء الـ action بدون إرجاع قيمة (void)
  $this->deleteAction->shouldReceive('execute')
    ->once()
    ->with($project);

  // 3. Act: تنفيذ الاختبار
  $this->service->delete($project);

  // لا نحتاج لـ expect لأن الـ method من نوع void
  // التأكد من الاستدعاء يكفي (Mockery يقوم بالتحقق تلقائياً في نهاية الاختبار)
});
