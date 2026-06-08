<?php

use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Requests\CreateDataTypeRequest;
use Illuminate\Support\Facades\App;

test('it creates DTO correctly with manual slug and data', function () {
  // 1. محاكاة الـ Container (Project Binding)
  $mockProject = new class {
    public $public_id = 'abc-123';
  };
  App::instance('currentProject', $mockProject);

  // 2. محاكاة الـ Repository
  $project = new \App\Models\Project();
  $project->id = 55;

  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldReceive('findByKey')
    ->with('abc-123')
    ->once()
    ->andReturn($project);
  App::instance(ProjectRepositoryInterface::class, $repoMock);

  // 3. تحضير الـ Request
  $request = new CreateDataTypeRequest();
  $request->merge([
    'name' => 'My Data Type',
    'slug' => 'custom-slug',
    'description' => 'Test description',
    'is_active' => false,
    'settings' => ['key' => 'value'],
  ]);

  // 4. التنفيذ
  $dto = CreateDataTypeDTO::fromRequest($request);

  // 5. التحقق
  expect($dto->project_id)->toBe(55)
    ->and($dto->name)->toBe('My Data Type')
    ->and($dto->slug)->toBe('custom-slug')
    ->and($dto->description)->toBe('Test description')
    ->and($dto->is_active)->toBeFalse()
    ->and($dto->settings)->toBe(['key' => 'value']);
});

test('it auto-generates slug from name when slug is missing', function () {
  // إعدادات البيئة (Mocking)
  $mockProject = new class {
    public $public_id = 'abc-123';
  };
  App::instance('currentProject', $mockProject);
  $project = new \App\Models\Project();
  $project->id = 55;
  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldReceive('findByKey')->andReturn($project);
  App::instance(ProjectRepositoryInterface::class, $repoMock);

  // Request بدون slug
  $request = new CreateDataTypeRequest();
  $request->merge([
    'name' => 'Super Data Type',
  ]);

  $dto = CreateDataTypeDTO::fromRequest($request);

  // التحقق أن الـ slug تم توليده تلقائياً (Str::slug)
  expect($dto->slug)->toBe('super-data-type');
});
