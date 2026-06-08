<?php

namespace Tests\Unit\Domains\CMS\Actions\DataType;

use App\Domains\CMS\Actions\DataType\DeleteDataTypeAction;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\DataType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it deletes datatype, clears all associated caches, and dispatches delete event', function () {
  // 1. تجهيز الموديل
  $dataType = new DataType();
  $dataType->id = 55;
  $dataType->slug = 'test-slug';
  $dataType->project_id = 10;

  // 2. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(DataTypeRepositoryInterface::class);

  $repoMock->shouldReceive('delete')
    ->once()
    ->with($dataType);

  // 3. تهيئة الـ Fakes
  Cache::spy();
  Event::fake();

  // 4. التنفيذ
  $action = new DeleteDataTypeAction($repoMock);
  $action->execute($dataType);

  // 5. التأكيدات
  // التأكد من مسح مفاتيح الكاش الثلاثة
  Cache::shouldHaveReceived('forget')->with(CacheKeys::dataType($dataType->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::dataTypeBySlug($dataType->slug, $dataType->project_id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::dataTypes($dataType->project_id));

  // التأكد من إطلاق الحدث الصحيح
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($dataType) {
    return $event->module === 'cms'
      && $event->eventType === 'delete_datatype'
      && $event->entityId === $dataType->id;
  });
});
