<?php

namespace Tests\Unit\Domains\CMS\Actions\DataType;

use App\Domains\CMS\Actions\DataType\UpdateDataTypeAction;
use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
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

test('it ensures unique slug, updates datatype, clears cache, and dispatches update event', function () {
  // 1. تجهيز الموديل
  $dataType = new DataType();
  $dataType->id = 55;
  $dataType->slug = 'old-slug';
  $dataType->project_id = 10;

  // 2. تجهيز الـ DTO (نستخدم Mock للـ DTO إذا كان بسيطاً، أو ننشئ كائناً حقيقياً)
  $dto = Mockery::mock(UpdateDataTypeDTO::class);
  $dto->slug = 'new-slug'; // نفترض أن الـ DTO يحتوي على خاصية slug

  // الموديل المحدث الذي سيعيده الـ Repository
  $updatedDataType = new DataType();
  $updatedDataType->id = 55;

  // 3. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(DataTypeRepositoryInterface::class);

  // نتوقع التحقق من تفرّد الـ Slug أولاً
  $repoMock->shouldReceive('ensureSlugIsUniqueForUpdate')
    ->once()
    ->with($dataType->project_id, $dto->slug, $dataType->id);

  // نتوقع عملية التحديث
  $repoMock->shouldReceive('update')
    ->once()
    ->with($dataType, $dto)
    ->andReturn($updatedDataType);

  // 4. تهيئة الـ Fakes
  Cache::spy();
  Event::fake();

  // 5. التنفيذ
  $action = new UpdateDataTypeAction($repoMock);
  $result = $action->execute($dataType, $dto);

  // 6. التأكيدات
  // التأكد من مسح مفاتيح الكاش الثلاثة
  Cache::shouldHaveReceived('forget')->with(CacheKeys::dataType($dataType->id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::dataTypeBySlug($dataType->slug, $dataType->project_id));
  Cache::shouldHaveReceived('forget')->with(CacheKeys::dataTypes($dataType->project_id));

  // التأكد من إطلاق الحدث الصحيح
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($dataType) {
    return $event->module === 'cms'
      && $event->eventType === 'update_datatype'
      && $event->entityId === $dataType->id;
  });

  // التأكد من أن الـ Action أعاد النتيجة المحدثة
  expect($result)->toBe($updatedDataType);
});
