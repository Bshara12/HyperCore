<?php

namespace Tests\Unit\Domains\CMS\Read\Actions\Field;

use App\Domains\CMS\Read\Actions\Field\IndexTrashedFields;
use App\Domains\CMS\Read\Repositories\Field\FieldRepositoryRead;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Models\DataType;

beforeEach(function () {
  // 1. عزل الـ CircuitBreaker والتأكد من استدعاء اسم الخدمة الصحيح
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')
      ->once()
      ->with('dataTypeField.indexTrashed') // يضمن تغطية الـ circuitServiceName
      ->andReturn(true);
    $mock->shouldIgnoreMissing();
  });
});

test('it retrieves trashed fields for a given data type', function () {
  // 2. تحضير الـ Mock للـ Repository
  $repo = mock(FieldRepositoryRead::class);

  // 3. تحضير كائن DataType حقيقي
  $dataType = new DataType();
  $dataType->id = 55;

  $expectedTrashedFields = [
    ['id' => 10, 'name' => 'Deleted Field 1'],
    ['id' => 11, 'name' => 'Deleted Field 2']
  ];

  // 4. تحديد التوقعات (ملاحظة: تأكد من اسم الدالة في الـ Repository هو indexTrashed)
  $repo->shouldReceive('indexTrashed')
    ->once()
    ->with($dataType)
    ->andReturn($expectedTrashedFields);

  // 5. التنفيذ
  $action = new IndexTrashedFields($repo);
  $results = $action->execute($dataType);

  // 6. التأكيد
  expect($results)->toBe($expectedTrashedFields);
});
