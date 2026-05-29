<?php

use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

test('it creates DTO correctly with provided values', function () {
  // 1. محاكاة الـ Current Project
  $mockProject = new class {
    public $public_id = 'my-project-key';
  };
  App::instance('currentProject', $mockProject);

  // 2. محاكاة الـ Repository
  $projectModel = new Project();
  $projectModel->id = 77;

  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldReceive('findByKey')
    ->with('my-project-key')
    ->once()
    ->andReturn($projectModel);

  App::instance(ProjectRepositoryInterface::class, $repoMock);

  // 3. محاكاة الـ Request
  $request = new Request([
    'from' => '2026-01-01',
    'to' => '2026-01-15',
    'period' => 'weekly',
    'limit' => 50
  ]);

  // 4. التنفيذ
  $dto = AnalyticsFilterDTO::fromRequest($request);

  // 5. التحقق
  expect($dto->projectId)->toBe(77)
    ->and($dto->from)->toBe('2026-01-01')
    ->and($dto->to)->toBe('2026-01-15')
    ->and($dto->period)->toBe('weekly')
    ->and($dto->limit)->toBe(50);
});

test('it uses default values when inputs are missing', function () {
  // إعداد مماثل للـ Mocking
  $mockProject = new class {
    public $public_id = 'test-key';
  };
  App::instance('currentProject', $mockProject);

  $projectModel = new Project();
  $projectModel->id = 1;
  $repoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $repoMock->shouldReceive('findByKey')->andReturn($projectModel);
  App::instance(ProjectRepositoryInterface::class, $repoMock);

  // إرسال طلب فارغ
  $request = new Request([]);

  $dto = AnalyticsFilterDTO::fromRequest($request);

  expect($dto->limit)->toBe(10) // القيمة الافتراضية
    ->and($dto->period)->toBe('daily'); // القيمة الافتراضية
});
