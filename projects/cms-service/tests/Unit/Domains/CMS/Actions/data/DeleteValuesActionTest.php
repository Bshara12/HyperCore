<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\DeleteValuesAction;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Event;
use Mockery;

test('it deletes values and dispatches log event', function () {
  // 1. Arrange: إعداد الموك والخدمات
  $circuitMock = Mockery::mock(CircuitBreakerService::class);
  $circuitMock->shouldReceive('canProceed')
    ->with('dataEntry.deleteValues')
    ->andReturn(true);
  $circuitMock->shouldIgnoreMissing();
  app()->instance(CircuitBreakerService::class, $circuitMock);

  $valuesMock = Mockery::mock(DataEntryValueRepository::class);
  $valuesMock->shouldReceive('deleteForEntry')
    ->once()
    ->with(999); // معرف الاختبار

  Event::fake();

  // 2. Act: التنفيذ
  $action = new DeleteValuesAction($valuesMock);
  $action->execute(999);

  // 3. Assert: التأكيدات
  Event::assertDispatched(SystemLogEvent::class, function ($event) {
    return $event->eventType === 'create_data_value' &&
      $event->entityId === 999;
  });
});
