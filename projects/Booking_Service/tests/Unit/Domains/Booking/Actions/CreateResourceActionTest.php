<?php

namespace Tests\Unit\Domains\Booking\Actions;

use App\Domains\Booking\Actions\CreateResourceAction;
use App\Domains\Booking\DTOs\ResourceDTO;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Models\Resource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

test('it creates a resource and forgets the project resources cache', function () {
  // 1. تجهيز الـ DTO والبيانات
  $projectId = 1;
  $dto = new ResourceDTO(
    name: 'New Meeting Room',
    projectId: $projectId,
    capacity: 10
  );

  $mockResource = new Resource();
  $mockResource->forceFill([
    'id' => 100,
    'project_id' => $projectId,
    'name' => 'New Meeting Room'
  ]);

  // 2. وضع بيانات في الكاش مسبقاً للتأكد من حذفها
  $cacheKey = CacheKeys::resources($projectId);
  Cache::put($cacheKey, ['some' => 'old_data']);
  expect(Cache::has($cacheKey))->toBeTrue();

  // 3. محاكاة المستودع
  $repository = Mockery::mock(ResourceRepositoryInterface::class);
  $repository->shouldReceive('create')
    ->once()
    ->with($dto)
    ->andReturn($mockResource);

  $action = new CreateResourceAction($repository);

  // 4. التنفيذ
  $result = $action->execute($dto);

  // 5. التحققات
  expect($result->id)->toBe(100);
  expect($result->project_id)->toBe($projectId);

  // التأكد من أن الكاش تم حذفه (Invalidation)
  expect(Cache::has($cacheKey))->toBeFalse();
});
