<?php

namespace Tests\Unit\Domains\Booking\Actions;

use App\Domains\Booking\Actions\UpdateResourceAction;
use App\Domains\Booking\DTOs\ResourceDTO;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Models\Resource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

test('it updates a resource and invalidates both single and list cache', function () {
  // 1. تجهيز البيانات
  $resourceId = 20;
  $projectId = 3;

  // إنشاء المورد الحالي
  $resource = (new Resource())->forceFill([
    'id' => $resourceId,
    'project_id' => $projectId,
    'name' => 'Old Room Name'
  ]);

  // تجهيز الـ DTO للبيانات الجديدة
  $dto = new ResourceDTO(
    name: 'Updated Meeting Room',
    projectId: $projectId,
    capacity: 15
  );

  $updatedResource = (new Resource())->forceFill([
    'id' => $resourceId,
    'project_id' => $projectId,
    'name' => 'Updated Meeting Room'
  ]);

  // 2. ملء الكاش للتأكد من حذفه
  $singleKey = CacheKeys::resource($resourceId);
  $listKey = CacheKeys::resources($projectId);
  Cache::put($singleKey, 'old_data');
  Cache::put($listKey, 'old_data');

  // 3. محاكاة المستودع
  $repository = Mockery::mock(ResourceRepositoryInterface::class);
  $repository->shouldReceive('update')
    ->once()
    ->with($resource, $dto)
    ->andReturn($updatedResource);

  $action = new UpdateResourceAction($repository);

  // 4. التنفيذ
  $result = $action->execute($resource, $dto);

  // 5. التحققات
  expect($result->name)->toBe('Updated Meeting Room');
  expect(Cache::has($singleKey))->toBeFalse('Individual resource cache was not cleared');
  expect(Cache::has($listKey))->toBeFalse('Project resources list cache was not cleared');
});
