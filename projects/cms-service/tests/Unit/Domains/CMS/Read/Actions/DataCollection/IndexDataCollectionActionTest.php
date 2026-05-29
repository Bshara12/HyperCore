<?php

namespace Tests\Unit\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Read\Actions\DataCollection\IndexDataCollectionAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Models\Project;

beforeEach(function () {
  // 1. عزل الـ CircuitBreaker لضمان نجاح الاختبار
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true);
    $mock->shouldReceive('recordSuccess')->andReturn(true);
    $mock->shouldIgnoreMissing();
  });
});

test('it retrieves and caches data collections for a project', function () {
  // 2. تحضير الـ Mocks
  $projectRepo = mock(ProjectRepositoryInterface::class);
  $dataRepo = mock(DataCollectionRepositoryInterface::class);

  // 3. تحضير البيانات
  $project = new Project();
  $project->id = 123;
  $expectedCollections = [
    ['id' => 1, 'name' => 'Collections A'],
    ['id' => 2, 'name' => 'Collections B']
  ];

  // 4. تحديد التوقعات
  $projectRepo->shouldReceive('findByKey')
    ->once()
    ->with('test-project-key')
    ->andReturn($project);

  $dataRepo->shouldReceive('list')
    ->once()
    ->with(123)
    ->andReturn($expectedCollections);

  // 5. التنفيذ
  $action = new IndexDataCollectionAction($dataRepo, $projectRepo);
  $results = $action->execute('test-project-key');

  // 6. التأكيد
  expect($results)->toBe($expectedCollections);
});
