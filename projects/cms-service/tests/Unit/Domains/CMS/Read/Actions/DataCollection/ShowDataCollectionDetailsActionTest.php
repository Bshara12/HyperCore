<?php

namespace Tests\Unit\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Read\Actions\DataCollection\ShowDataCollectionDetailsAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Models\Project;

beforeEach(function () {
  // 1. عزل الـ CircuitBreaker
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true);
    $mock->shouldIgnoreMissing();
  });
});

test('it retrieves collection details and injects its items', function () {
  // 1. تحضير الـ Mocks
  $projectRepo = mock(ProjectRepositoryInterface::class);
  $dataRepo = mock(DataCollectionRepositoryInterface::class);

  // 2. تحضير البيانات (استخدام الـ Model بدلاً من مصفوفة)
  $project = new \App\Models\Project();
  $project->id = 123;

  // إنشاء كائن حقيقي من الـ Model
  $mockCollection = new \App\Models\DataCollection();
  $mockCollection->id = 456; // تعيين الـ ID الذي يستخدمه الـ Action

  $mockItems = [
    ['id' => 1, 'title' => 'Item 1'],
    ['id' => 2, 'title' => 'Item 2']
  ];

  // 3. تحديد التوقعات (Expectations)
  $projectRepo->shouldReceive('findByKey')
    ->once()
    ->with('test-project-key')
    ->andReturn($project);

  // إرجاع الكائن بدلاً من المصفوفة
  $dataRepo->shouldReceive('find')
    ->once()
    ->with(123, 'collection-slug')
    ->andReturn($mockCollection);

  $dataRepo->shouldReceive('getCollectionItems')
    ->once()
    ->with(456)
    ->andReturn($mockItems);

  // 4. التنفيذ
  $action = new ShowDataCollectionDetailsAction($dataRepo, $projectRepo);
  $results = $action->execute('test-project-key', 'collection-slug');

  // 5. التأكيد
  // ملاحظة: بما أننا نرجع Model، فإن التحقق سيكون على الكائن
  expect($results)->toBeInstanceOf(\App\Models\DataCollection::class)
    ->and($results->items)->toBe($mockItems); // التحقق من الدمج
});
