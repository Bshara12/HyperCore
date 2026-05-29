<?php

namespace Tests\Unit\Domains\CMS\Actions\DataType;

use App\Domains\CMS\Actions\DataType\CreateDataTypeAction;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Models\DataType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it ensures unique slug, creates datatype, clears cache, and dispatches log event', function () {
  // 1. تجهيز الـ DTO
  // تأكد من تمرير الخصائص التي يتوقعها الـ Constructor في الـ DTO الخاص بك
  $dto = new CreateDataTypeDTO(
    project_id: 1,
    name: 'Test Data Type', // المعامل المفقود الذي كان يسبب الخطأ
    slug: 'test-data-type',
    description: 'This is a test description' // أضف أي معاملات اختيارية أخرى إذا لزم الأمر
  );

  // 2. تجهيز الموديل الذي سيعيده الـ Repository
  $dataType = new DataType();
  $dataType->id = 99;

  // 3. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(DataTypeRepositoryInterface::class);

  // أولاً: عرّف التوقع الخاص بـ ensureSlugIsUnique
  $repoMock->shouldReceive('ensureSlugIsUnique')
    ->once()
    ->with((int)$dto->project_id, $dto->slug);

  // ... باقي الكود كما هو
  $repoMock->shouldReceive('create')
    ->once()
    ->with($dto)
    ->andReturn($dataType);

  // 4. تهيئة الـ Fakes
  Cache::spy();
  Event::fake();

  // 5. التنفيذ
  $action = new CreateDataTypeAction($repoMock);
  $result = $action->execute($dto);

  // 6. التأكيدات
  // التأكد من مسح الكاش الخاص بالـ project
  Cache::shouldHaveReceived('forget')->with(CacheKeys::dataTypes((int)$dto->project_id));

  // التأكد من إطلاق الحدث الصحيح
  Event::assertDispatched(SystemLogEvent::class, function ($event) {
    return $event->module === 'cms'
      && $event->eventType === 'create_datatype';
  });

  // التأكد من النتيجة
  expect($result)->toBe($dataType);
});
