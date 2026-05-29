<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\CreateDataCollectionAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use App\Models\DataCollection; // تأكد من إضافة هذا الـ import

uses(RefreshDatabase::class); // 2. فعل هذه الخاصية لـ Pest

afterEach(function () {
  Mockery::close();
});

test('it creates data collection, clears cache, and dispatches event', function () {
  // 1. تجهيز الـ DTO (نستخدم stdClass لتمثيل الـ DTO)
  $dto = new \stdClass();
  $dto->project_id = 123;

  // 2. تجهيز الموكات
  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);
  $mockCollection = new DataCollection();
  // نتوقع استدعاء الـ create في الريبو
  $repoMock->shouldReceive('create')
    ->once()
    ->with($dto)
    ->andReturn($mockCollection);

  // 3. Fake للـ Events (مهم جداً لاختبار الأحداث)
  Event::fake();

  // 4. Cache Facade (نتوقع أن يتم مسح الكاش)
  Cache::shouldReceive('forget')
    ->once()
    ->with(CacheKeys::collections($dto->project_id));

  // 5. التنفيذ
  $action = new CreateDataCollectionAction($repoMock);
  $result = $action->execute($dto);

  // 6. التأكيدات
  expect($result)->toBe($mockCollection);

  // التأكد من أن الحدث تم إطلاقه
  Event::assertDispatched(SystemLogEvent::class, function ($event) {
    return $event->module === 'cms' && $event->eventType === 'collection_create';
  });
});
