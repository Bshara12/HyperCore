<?php

use App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Requests\DeactivateCollectionRequest;
use App\Models\Project;
use Illuminate\Support\Facades\App;

test('it creates DTO from request and resolves dependencies from container', function () {
  // 1. محاكاة المشروع الحالي
  $mockProject = new class {
    public $public_id = 'abc-123';
  };
  App::instance('currentProject', $mockProject);

  // 2. إنشاء كائن Project حقيقي
  $project = new Project();
  $project->id = 99;

  // 3. محاكاة الـ Repository
  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldReceive('findByKey')
    ->with('abc-123')
    ->once()
    ->andReturn($project);
  App::instance(ProjectRepositoryInterface::class, $repoMock);

  // 4. استخدام نسخة حقيقية من الـ Request ودمج البيانات
  $request = new DeactivateCollectionRequest();
  $request->merge([
    'is_active' => true,
  ]);

  // 5. تنفيذ التحويل (مع تمرير الـ slug يدوياً كما في الكود)
  $dto = DeactivateCollectionDTO::fromRequest('my-collection-slug', $request);

  // 6. التحقق من النتائج
  expect($dto->project_id)->toBe(99)
    ->and($dto->slug)->toBe('my-collection-slug')
    ->and($dto->is_active)->toBeTrue();
});

test('it uses default value when is_active is missing in request', function () {
  // إعدادات مسبقة للـ Container (نفس الخطوات السابقة)
  $mockProject = new class {
    public $public_id = 'abc-123';
  };
  App::instance('currentProject', $mockProject);
  $project = new Project();
  $project->id = 99;
  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldReceive('findByKey')->andReturn($project);
  App::instance(ProjectRepositoryInterface::class, $repoMock);

  // الـ Request هنا فارغ بدون merge
  $request = new DeactivateCollectionRequest();

  $dto = DeactivateCollectionDTO::fromRequest('my-slug', $request);

  // التحقق من أن القيمة الافتراضية false قد تم تطبيقها
  expect($dto->is_active)->toBeFalse();
});
