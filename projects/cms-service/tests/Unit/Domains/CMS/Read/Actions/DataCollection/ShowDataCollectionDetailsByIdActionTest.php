<?php

namespace Tests\Unit\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Read\Actions\DataCollection\ShowDataCollectionDetailsByIdAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Models\DataCollection;

beforeEach(function () {
  // 1. عزل الـ CircuitBreaker
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true);
    $mock->shouldIgnoreMissing();
  });
});

test('it retrieves collection details by id and injects items', function () {
  // 2. تحضير الـ Mock للـ Repository
  $repo = mock(DataCollectionRepositoryInterface::class);

  // 3. تحضير البيانات
  $mockCollection = new DataCollection();
  $mockCollection->id = 789;

  $mockItems = [
    ['id' => 1, 'name' => 'Item 1'],
    ['id' => 2, 'name' => 'Item 2']
  ];

  // 4. تحديد التوقعات
  $repo->shouldReceive('findById')
    ->once()
    ->with(789)
    ->andReturn($mockCollection);

  $repo->shouldReceive('getCollectionItems')
    ->once()
    ->with(789)
    ->andReturn($mockItems);

  // 5. التنفيذ
  $action = new ShowDataCollectionDetailsByIdAction($repo);
  $results = $action->execute(789);

  // 6. التأكيد (Assertion)
  // التأكد من أن الـ Action قام بدمج الـ items في الـ collection بنجاح
  expect($results)->toBeInstanceOf(DataCollection::class)
    ->and($results['items'])->toBe($mockItems);
});
