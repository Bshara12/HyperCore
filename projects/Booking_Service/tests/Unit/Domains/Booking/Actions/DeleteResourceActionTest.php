<?php

namespace Tests\Unit\Domains\Booking\Actions;

use App\Domains\Booking\Actions\DeleteResourceAction;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Models\Resource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
  // ضروري لدعم Tags في بيئة الاختبار
  config(['cache.default' => 'array']);
});

test('it deletes resource and clears all related cache including tags', function () {
  // 1. تجهيز المورد
  $resourceId = 5;
  $projectId = 1;
  $resource = new Resource();
  $resource->forceFill([
    'id' => $resourceId,
    'project_id' => $projectId
  ]);

  // 2. ملء الكاش ببيانات وهمية للتأكد من حذفها
  $singleKey = CacheKeys::resource($resourceId);
  $listKey = CacheKeys::resources($projectId);
  $tagName = "resource_{$resourceId}_bookings";

  Cache::put($singleKey, 'old_single_data');
  Cache::put($listKey, 'old_list_data');
  Cache::tags([$tagName])->put('booking_1', 'some_booking');

  // 3. محاكاة المستودع
  $repository = Mockery::mock(ResourceRepositoryInterface::class);
  $repository->shouldReceive('delete')
    ->once()
    ->with($resource)
    ->andReturn(true);

  $action = new DeleteResourceAction($repository);

  // 4. التنفيذ
  $action->execute($resource);

  // 5. التحققات من الكاش
  expect(Cache::has($singleKey))->toBeFalse('Single resource cache should be forgotten');
  expect(Cache::has($listKey))->toBeFalse('Project resources list cache should be forgotten');

  // التحقق من تفريغ الـ Tags
  expect(Cache::tags([$tagName])->has('booking_1'))->toBeFalse('Resource bookings tags should be flushed');
});
