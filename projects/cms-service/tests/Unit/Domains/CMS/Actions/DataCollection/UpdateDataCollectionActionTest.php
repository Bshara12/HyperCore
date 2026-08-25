<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\UpdateDataCollectionAction;
use App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\DataCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

beforeEach(function () {
  $this->mock(\App\Domains\Core\Services\CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true);
    $mock->shouldIgnoreMissing();
  });
});

afterEach(function () {
  Mockery::close();
});

test('it updates collection, clears all associated caches, and dispatches update event', function () {
  $dto = new UpdateDataCollectionDTO(
    collection_id: 55,
    project_id: 10,
    slug: 'my-collection',
    data: [
      'name' => 'Updated Name',
      'is_active' => true,
    ]
  );

  $collection = new DataCollection();
  $collection->id = 55;
  $collection->project_id = 10;
  $collection->slug = 'my-collection';

  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);

  $repoMock->shouldReceive('update')
    ->once()
    ->with($dto)
    ->andReturn($collection);

  Cache::spy();
  Event::fake();

  $action = new UpdateDataCollectionAction($repoMock);
  $result = $action->execute($dto);

  foreach ([false, true] as $includeInactive) {
    Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionById(55, $includeInactive));
    Cache::shouldHaveReceived('forget')->with(CacheKeys::collections(10, $includeInactive));
    Cache::shouldHaveReceived('forget')->with(CacheKeys::collection(10, 'my-collection', $includeInactive));
  }

  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionItems(55));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionEntries(55));

  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($dto) {
    return $event->module === 'cms'
      && $event->eventType === 'update_collection'
      && $event->entityId === $dto->collection_id;
  });

  expect($result)->toBe($collection);
});

test('it also clears the cache key of the slug the collection was addressed by', function () {
  // Guard against a regression of the rename bug: if a slug ever changes again,
  // the key the collection was *found* under must be invalidated too, otherwise
  // it keeps serving the pre-update payload for the rest of the TTL.
  $dto = new UpdateDataCollectionDTO(
    collection_id: 55,
    project_id: 10,
    slug: 'old-slug',
    data: ['name' => 'New Name']
  );

  $collection = new DataCollection();
  $collection->id = 55;
  $collection->project_id = 10;
  $collection->slug = 'new-slug';

  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);
  $repoMock->shouldReceive('update')->once()->with($dto)->andReturn($collection);

  Cache::spy();
  Event::fake();

  (new UpdateDataCollectionAction($repoMock))->execute($dto);

  Cache::shouldHaveReceived('forget')->with(CacheKeys::collection(10, 'old-slug', false));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collection(10, 'new-slug', false));
});
