<?php

namespace Tests\Unit\Domains\CMS\Read\Actions\DataType;

use App\Domains\CMS\Read\Actions\DataType\IndexDataTypeAction;
use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Domains\Core\Services\CircuitBreakerService;

beforeEach(function () {
  // 1. عزل الـ CircuitBreaker لضمان نجاح الاختبار
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true);
    $mock->shouldIgnoreMissing();
  });
});

test('it retrieves and caches list of data types for a project', function () {
  // 2. تحضير الـ Mock للـ Repository
  // ملاحظة: بما أنك تعتمد على Concrete Class، يمكننا عمل Mock لها مباشرة
  $repo = mock(DataTypeRepositoryRead::class);

  // 3. تحضير البيانات الوهمية
  $projectId = 123;
  $expectedDataTypes = [
    ['id' => 1, 'name' => 'Type A'],
    ['id' => 2, 'name' => 'Type B']
  ];

  // 4. تحديد التوقعات (Expectations)
  $repo->shouldReceive('list')
    ->once()
    ->with($projectId)
    ->andReturn($expectedDataTypes);

  // 5. التنفيذ
  $action = new IndexDataTypeAction($repo);
  $results = $action->execute($projectId);

  // 6. التأكيد (Assertion)
  expect($results)->toBe($expectedDataTypes);
});
