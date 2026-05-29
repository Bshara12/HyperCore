<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\InsertValuesAction;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Event;
use Mockery;

// إعداد مشترك للـ CircuitBreaker لتجنب تكرار الكود
beforeEach(function () {
  $circuitMock = Mockery::mock(CircuitBreakerService::class);
  $circuitMock->shouldReceive('canProceed')
    ->with('dataEntry.insertValues')
    ->andReturn(true);
  $circuitMock->shouldIgnoreMissing();
  app()->instance(CircuitBreakerService::class, $circuitMock);
});

test('it performs bulk insert and dispatches log event', function () {
  // 1. Arrange: إعداد المستودع (Repository)
  $valuesMock = Mockery::mock(DataEntryValueRepository::class);

  $entryId = 10;
  $dataTypeId = 5;
  $data = [
    ['field' => 'name', 'value' => 'Test'],
    ['field' => 'age', 'value' => '25'],
  ];

  // التوقعات
  $valuesMock->shouldReceive('bulkInsert')
    ->once()
    ->with($entryId, $dataTypeId, $data);

  // تزييف الأحداث
  Event::fake();

  // 2. Act: تنفيذ العملية
  $action = new InsertValuesAction($valuesMock);
  $action->execute($entryId, $dataTypeId, $data);

  // 3. Assert: التأكيدات
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($entryId) {
    return $event->eventType === 'create_value' &&
      $event->entityId === $entryId;
  });
});
