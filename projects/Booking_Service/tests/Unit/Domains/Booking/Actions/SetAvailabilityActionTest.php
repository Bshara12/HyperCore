<?php

namespace Tests\Unit\Domains\Booking\Actions;

use App\Domains\Booking\Actions\SetAvailabilityAction;
use App\Domains\Booking\DTOs\AvailabilityDTO;
use App\Domains\Booking\Repositories\Interface\ResourceRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Models\Resource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

test('it sets availabilities and clears related resource cache', function () {
  $resourceId = 10;
  $projectId = 1;
  $resource = (new Resource())->forceFill(['id' => $resourceId, 'project_id' => $projectId]);

  // البيانات المصححة
  $availabilitiesData = [
    [
      'day_of_week' => 1,
      'start_time' => '08:00',
      'end_time' => '16:00',
      'slot_duration' => 30
    ],
  ];

  $singleKey = CacheKeys::resource($resourceId);
  $listKey = CacheKeys::resources($projectId);
  Cache::put($singleKey, 'old');
  Cache::put($listKey, 'old');

  $repository = Mockery::mock(ResourceRepositoryInterface::class);
  $repository->shouldReceive('setAvailabilities')
    ->once()
    ->with($resource, Mockery::on(function ($dtos) {
      return is_array($dtos) && $dtos[0] instanceof AvailabilityDTO;
    }));

  $action = new SetAvailabilityAction($repository);
  $action->execute($resource, $availabilitiesData);

  expect(Cache::has($singleKey))->toBeFalse();
  expect(Cache::has($listKey))->toBeFalse();
});
