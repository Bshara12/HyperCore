<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\DeleteDataCollectionItemsAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it deletes collection items, clears associated cache, and dispatches system log event', function () {
  // 1. تجهيز المعطيات
  $collectionId = 123;

  // 2. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);

  // نتوقع استدعاء دالة deleteItems
  $repoMock->shouldReceive('deleteItems')
    ->once()
    ->with($collectionId);

  // 3. تهيئة الـ Fakes
  Cache::spy();
  Event::fake();

  // 4. التنفيذ
  $action = new DeleteDataCollectionItemsAction($repoMock);
  $action->execute($collectionId);

  // 5. التأكيدات (Assertions)

  // التأكد من مسح المفاتيح الثلاثة في الكاش
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionItems($collectionId));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionEntries($collectionId));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionById($collectionId));

  // التأكد من إطلاق الحدث بالمعلومات الصحيحة
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($collectionId) {
    return $event->module === 'cms'
      && $event->eventType === 'delete_collection'
      && $event->entityId === $collectionId;
  });
});
