<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\DeactivateCollectionAction;
use App\Domains\CMS\DTOs\DataCollection\DeactivateCollectionDTO;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
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

test('it deactivates collection, clears cache, and dispatches system log event', function () {
    $dto = new DeactivateCollectionDTO(
        project_id: 99,
        slug: 'test-collection',
        is_active: false
    );

    $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);

    $repoMock->shouldReceive('deactivate')
        ->once()
        ->with($dto);

    Event::fake();
    Cache::spy();

    $action = new DeactivateCollectionAction($repoMock);
    $action->execute($dto);

    Cache::shouldHaveReceived('forget')->with(CacheKeys::collection(99, 'test-collection', false));
    Cache::shouldHaveReceived('forget')->with(CacheKeys::collection(99, 'test-collection', true));
    Cache::shouldHaveReceived('forget')->with(CacheKeys::collections(99, false));
    Cache::shouldHaveReceived('forget')->with(CacheKeys::collections(99, true));

    Event::assertDispatched(SystemLogEvent::class, function ($event) use ($dto) {
        return $event->module === 'cms'
            && $event->eventType === 'deactivate_collection'
            && $event->entityId === $dto->slug;
    });
});
