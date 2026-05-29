<?php

namespace Tests\Unit\Domains\CMS\Read\Actions\DataType;

use App\Domains\CMS\Read\Actions\DataType\IndexTrashedDataType;
use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Domains\Core\Services\CircuitBreakerService;

beforeEach(function () {
  // عزل الـ Circuit Breaker لضمان عدم الاتصال بقاعدة البيانات
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true);
    $mock->shouldIgnoreMissing();
  });
});

test('it retrieves trashed data types for a project', function () {
  // 1. تحضير الـ Mock للـ Repository
  $repo = mock(DataTypeRepositoryRead::class);

  // 2. تحضير البيانات الوهمية
  $projectId = 999;
  $expectedTrashedItems = [
    ['id' => 10, 'name' => 'Deleted Type A'],
    ['id' => 11, 'name' => 'Deleted Type B']
  ];

  // 3. تحديد التوقعات: نتوقع استدعاء الدالة 'trashed' مرة واحدة فقط
  $repo->shouldReceive('trashed')
    ->once()
    ->with($projectId)
    ->andReturn($expectedTrashedItems);

  // 4. تنفيذ الـ Action
  $action = new IndexTrashedDataType($repo);
  $results = $action->execute($projectId);

  // 5. التأكيد (Assertion)
  expect($results)->toBe($expectedTrashedItems);
});
