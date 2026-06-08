<?php

namespace Tests\Unit\Domains\CMS\Actions\Field;

use App\Domains\CMS\Actions\Field\ForceDeleteAction;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Events\SystemLogEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it force deletes field and dispatches log event', function () {
  // 1. تجهيز المعطيات
  $fieldId = 99;

  // 2. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);

  // نتوقع استدعاء forceDelete بالـ ID الصحيح
  $repoMock->shouldReceive('forceDelete')
    ->once()
    ->with($fieldId);

  // 3. تهيئة الـ Fakes
  Event::fake();

  // 4. التنفيذ
  $action = new ForceDeleteAction($repoMock);
  $action->execute($fieldId);

  // 5. التأكيدات
  // التأكد من إطلاق الحدث الصحيح بالـ ID الممرر
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($fieldId) {
    return $event->module === 'cms'
      && $event->eventType === 'force_delete_field'
      && $event->entityId === $fieldId;
  });
});
