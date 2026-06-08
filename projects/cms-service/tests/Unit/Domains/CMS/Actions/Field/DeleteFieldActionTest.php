<?php

namespace Tests\Unit\Domains\CMS\Actions\Field;

use App\Domains\CMS\Actions\Field\DeleteFieldAction;
use App\Domains\CMS\Repositories\Interface\FieldRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\DataTypeField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it deletes field, clears cache, and dispatches delete event', function () {
  // 1. تجهيز الموديل
  $field = new DataTypeField();
  $field->id = 77;
  $field->data_type_id = 10;

  // 2. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(FieldRepositoryInterface::class);
  $repoMock->shouldReceive('delete')
    ->once()
    ->with($field);

  // 3. تهيئة الـ Fakes
  Cache::spy();
  Event::fake();

  // 4. التنفيذ
  $action = new DeleteFieldAction($repoMock);
  $action->execute($field);

  // 5. التأكيدات
  // التأكد من مسح الكاش الخاص بالحقول التابعة للـ DataType
  Cache::shouldHaveReceived('forget')->with(CacheKeys::fields($field->data_type_id));

  // التأكد من إطلاق الحدث الصحيح
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($field) {
    return $event->module === 'cms'
      && $event->eventType === 'delete_field'
      && $event->entityId === $field->id;
  });
});
