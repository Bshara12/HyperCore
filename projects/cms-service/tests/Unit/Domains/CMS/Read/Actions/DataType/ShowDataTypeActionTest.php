<?php

namespace Tests\Unit\Domains\CMS\Read\Actions\DataType;

use App\Domains\CMS\Read\Actions\DataType\ShowDataTypeAction;
use App\Domains\CMS\Read\DTOs\DataType\ShowDataTypeDTOProperities;
use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Domains\Core\Services\CircuitBreakerService;

beforeEach(function () {
  // 1. عزل الـ CircuitBreaker
  $this->mock(CircuitBreakerService::class, function ($mock) {
    // هنا نضيف التحدي:
    // نطلب منه التأكد أن الدالة تم استدعاؤها بـ 'dataType.show' تحديداً
    $mock->shouldReceive('canProceed')
      ->once() // نضمن أنها استدعيت مرة واحدة
      ->with('dataType.show') // هذا هو "الاختبار" الحقيقي للدالة المحمية
      ->andReturn(true);

    $mock->shouldIgnoreMissing();
  });
});

test('it retrieves and caches data type details using DTO', function () {
  // 1. تحضير الـ Mock للـ Repository
  $repo = mock(DataTypeRepositoryRead::class);

  // 2. تحضير الـ DTO
  $dto = new ShowDataTypeDTOProperities(
    project_id: 100,
    slug: 'test-slug'
  );

  // 3. تحضير كائن DataType حقيقي بدلاً من المصفوفة
  $mockDataType = new \App\Models\DataType();
  $mockDataType->id = 1;
  $mockDataType->name = 'Test Data Type';

  // 4. تحديد التوقعات (إرجاع الكائن بدلاً من المصفوفة)
  $repo->shouldReceive('findBySlug')
    ->once()
    ->with('test-slug', 100)
    ->andReturn($mockDataType);

  // 5. التنفيذ
  $action = new ShowDataTypeAction($repo);
  $results = $action->execute($dto);

  // 6. التأكيد
  // التأكد من أن النتيجة هي كائن DataType وليست مصفوفة
  expect($results)->toBeInstanceOf(\App\Models\DataType::class)
    ->and($results->id)->toBe(1)
    ->and($results->name)->toBe('Test Data Type');
});
