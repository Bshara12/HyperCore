<?php

namespace Tests\Unit\Domains\CMS\Actions\DataType;

use App\Domains\CMS\Actions\DataType\ForceDeleteAction;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Events\SystemLogEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it force deletes datatype and dispatches log event', function () {
  // 1. تجهيز المعطيات
  $dataTypeId = 99;

  // 2. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(DataTypeRepositoryInterface::class);

  // نتوقع استدعاء forceDelete بالـ ID الصحيح
  $repoMock->shouldReceive('forceDelete')
    ->once()
    ->with($dataTypeId);

  // 3. تهيئة الـ Fakes
  Event::fake();

  // 4. التنفيذ
  $action = new ForceDeleteAction($repoMock);
  $action->execute($dataTypeId);

  // 5. التأكيدات
  // التأكد من إطلاق الحدث الصحيح بالـ ID الممرر
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($dataTypeId) {
    return $event->module === 'cms'
      && $event->eventType === 'force_delete_datatype'
      && $event->entityId === $dataTypeId;
  });
});
