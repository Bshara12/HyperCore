<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\UpdateDataCollectionAction;
use App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO;
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

test('it updates collection, clears all associated caches, and dispatches update event', function () {
  // 1. إنشاء الـ DTO الحقيقي بدلاً من stdClass
  $dto = new UpdateDataCollectionDTO(
    collection_id: 55,
    data: [
      'name' => 'Updated Name',
      'is_active' => true
    ]
  );

  // 2. تجهيز الموديل
  $collection = new DataCollection();
  $collection->id = 55;
  $collection->project_id = 10;

  // 3. تجهيز الـ Mock
  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);

  // الآن الـ update يتوقع بالضبط هذا النوع من الـ DTO
  $repoMock->shouldReceive('update')
    ->once()
    ->with($dto)
    ->andReturn($collection);

  // 4. تهيئة الـ Fakes
  Cache::spy();
  Event::fake();

  // 5. التنفيذ
  $action = new UpdateDataCollectionAction($repoMock);
  $result = $action->execute($dto);

  // 6. التأكيدات
  // التأكد من مسح مفاتيح الكاش الأربعة
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionById($dto->collection_id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionItems($dto->collection_id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionEntries($dto->collection_id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collections($collection->project_id));

  // التأكد من إطلاق الحدث بالمعلومات الصحيحة
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($dto) {
    return $event->module === 'cms'
      && $event->eventType === 'update_collection'
      && $event->entityId === $dto->collection_id;
  });

  // التأكد من أن الـ Action أعاد الموديل المحدث
  expect($result)->toBe($collection);
});
