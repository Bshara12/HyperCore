<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\InsertCollectionItemsAction;
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

test('it inserts items, clears cache, and dispatches log event', function () {
  // 1. تجهيز الـ DTO الوهمي
  $dto = new \stdClass();
  $dto->collectionSlug = 'test-collection';
  $dto->slug = 'test-collection'; // يُستخدم في الـ Event
  $dto->items = [101, 102, 103];

  // 2. تجهيز الموديل الحقيقي (لتجنب الـ TypeError)
  $collection = new DataCollection();
  $collection->id = 55;

  // 3. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);

  // نتوقع جلب الـ collection عبر الـ slug
  $repoMock->shouldReceive('getBySlug')
    ->once()
    ->with($dto->collectionSlug)
    ->andReturn($collection);

  // نتوقع استدعاء insertItems بالـ ID والعناصر
  $repoMock->shouldReceive('insertItems')
    ->once()
    ->with($collection->id, $dto->items);

  // 4. تهيئة الـ Fakes
  Cache::spy();
  Event::fake();

  // 5. التنفيذ
  $action = new InsertCollectionItemsAction($repoMock);
  $action->execute($dto);

  // 6. التأكيدات
  // التأكد من مسح مفاتيح الكاش الصحيحة
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionItems($collection->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionEntries($collection->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionById($collection->id));

  // التأكد من إطلاق الحدث بالمعلومات الصحيحة
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($dto) {
    return $event->module === 'cms'
      && $event->eventType === 'add_collection_item'
      && $event->entityId === $dto->slug;
  });
});
