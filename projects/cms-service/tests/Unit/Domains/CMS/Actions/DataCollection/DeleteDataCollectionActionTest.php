<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\DeleteDataCollectionAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\DataCollection; // 1. تأكد من استيراد الموديل
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it deletes collection, clears all caches, and dispatches delete event', function () {
  // 1. تجهيز الموديل الحقيقي بدلاً من stdClass
  $collection = new DataCollection();
  $collection->id = 123;
  $collection->project_id = 1;
  $slug = 'test-collection';

  // 2. تجهيز الـ Repository
  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);

  $repoMock->shouldReceive('getBySlug')
    ->once()
    ->with($slug)
    ->andReturn($collection); // الآن الـ Return مطابق للنوع المطلوب

  $repoMock->shouldReceive('delete')
    ->once()
    ->with($collection->id);

  // 3. استخدم Spy للـ Cache (لتجنب أخطاء الفاساد)
  Cache::spy();

  Event::fake();

  // 4. التنفيذ
  $action = new DeleteDataCollectionAction($repoMock);
  $action->execute($slug);

  // 5. التأكيدات
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collection($collection->project_id, $slug));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionById($collection->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionItems($collection->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collectionEntries($collection->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::collections($collection->project_id));

  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($collection) {
    return $event->module === 'cms'
      && $event->eventType === 'delete_collection'
      && $event->entityId === $collection->id;
  });
});
