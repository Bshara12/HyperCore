<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\CreateDataCollectionAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\DataCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

/*
 | No RefreshDatabase here on purpose. The repository is mocked, so this test
 | needs no schema — and RefreshDatabase wraps the whole test in a transaction,
 | which permanently defers the DB::afterCommit callbacks the cache invalidation
 | now runs on. Without it the action's transaction commits to level 0 and the
 | callbacks fire, exactly as they do in production.
 */

beforeEach(function () {
  // Isolated explicitly: without a schema the real service would query
  // circuit_breakers.
  $this->mock(\App\Domains\Core\Services\CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true);
    $mock->shouldIgnoreMissing();
  });
});

afterEach(function () {
  Mockery::close();
});

test('it creates data collection, clears cache, and dispatches event', function () {
  $dto = new \stdClass();
  $dto->project_id = 123;

  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);
  $mockCollection = new DataCollection();
  $mockCollection->id = 7;

  $repoMock->shouldReceive('create')
    ->once()
    ->with($dto)
    ->andReturn($mockCollection);

  Event::fake();
  Cache::spy();

  $action = new CreateDataCollectionAction($repoMock);
  $result = $action->execute($dto);

  expect($result)->toBe($mockCollection);

  // Both visibility variants of the project list must go, not just the public one.
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collections(123, false));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collections(123, true));

  Event::assertDispatched(SystemLogEvent::class, function ($event) {
    return $event->module === 'cms' && $event->eventType === 'collection_create';
  });
});
