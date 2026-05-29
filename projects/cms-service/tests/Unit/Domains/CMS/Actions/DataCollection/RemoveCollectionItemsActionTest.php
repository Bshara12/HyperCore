<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\RemoveCollectionItemsAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\DataCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it removes items, clears cache, and dispatches log event', function () {
  // 1. تجهيز الـ DTO
  $dto = new \stdClass();
  $dto->collectionSlug = 'test-collection';
  $dto->items = [101, 102];

  // 2. تجهيز الموديل الحقيقي لضمان تطابق النوع
  $collection = new DataCollection();
  $collection->id = 55;

  // 3. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);

  $repoMock->shouldReceive('getBySlug')
    ->once()
    ->with($dto->collectionSlug)
    ->andReturn($collection);

  // نتوقع استدعاء removeItems بالـ ID والعناصر
  $repoMock->shouldReceive('removeItems')
    ->once()
    ->with($collection->id, $dto->items);

  // 4. تهيئة الـ Fakes
  Cache::spy();
  Event::fake();

  // 5. التنفيذ
  $action = new RemoveCollectionItemsAction($repoMock);
  $action->execute($dto);

  // 6. التأكيدات
  // التأكد من مسح مفاتيح الكاش الثلاثة
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionItems($collection->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionEntries($collection->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionById($collection->id));

  // التأكد من إطلاق الحدث بالمعلومات الصحيحة
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($dto) {
    return $event->module === 'cms'
      && $event->eventType === 'delete_collection_item'
      && $event->entityId === $dto->collectionSlug;
  });
});
