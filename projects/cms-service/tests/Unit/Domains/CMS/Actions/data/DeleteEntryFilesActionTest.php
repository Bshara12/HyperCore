<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\DeleteEntryFilesAction;
use App\Domains\CMS\Repositories\Interface\DataEntryValueRepository;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Event;
use Mockery;

test('it collects only valid project files and dispatches log event', function () {
  // 1. Arrange: إعداد الـ Mocks والـ CircuitBreaker
  $circuitMock = Mockery::mock(CircuitBreakerService::class);
  $circuitMock->shouldReceive('canProceed')
    ->with('dataEntry.collectFiles')
    ->andReturn(true);
  $circuitMock->shouldIgnoreMissing();
  app()->instance(CircuitBreakerService::class, $circuitMock);

  $valuesMock = Mockery::mock(DataEntryValueRepository::class);

  // محاكاة مجموعة بيانات متنوعة (صحيحة، فارغة، وخاطئة)
  $valuesMock->shouldReceive('getForEntry')
    ->with(123)
    ->andReturn([
      ['value' => 'projects/image.jpg'],     // صحيح
      ['value' => null],                     // فارغ (يجب تجاهله)
      ['value' => 'uploads/other.png'],      // لا يحتوي 'projects/' (يجب تجاهله)
      ['value' => 'projects/document.pdf'],  // صحيح
    ]);

  Event::fake();

  // 2. Act: التنفيذ
  $action = new DeleteEntryFilesAction($valuesMock);
  $result = $action->execute(123);

  // 3. Assert: التأكيدات

  // التأكد من أن الـ Action أرجع المسارات الصحيحة فقط
  expect($result)->toHaveCount(2)
    ->and($result)->toContain('projects/image.jpg', 'projects/document.pdf')
    ->and($result)->not->toContain('uploads/other.png');

  // التأكد من إطلاق حدث الـ Log
  Event::assertDispatched(SystemLogEvent::class, function ($event) {
    return $event->module === 'cms' &&
      $event->eventType === 'storage_file' &&
      $event->entityId === 123;
  });
});
