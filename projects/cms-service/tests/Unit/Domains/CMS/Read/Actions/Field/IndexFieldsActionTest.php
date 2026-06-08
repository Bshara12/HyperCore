<?php

namespace Tests\Unit\Domains\CMS\Read\Actions\Field;

use App\Domains\CMS\Read\Actions\Field\IndexFieldsAction;
use App\Domains\CMS\Read\Repositories\Field\FieldRepositoryRead;
use App\Domains\Core\Services\CircuitBreakerService;
use App\Models\DataType;

beforeEach(function () {
    // 1. عزل الـ CircuitBreaker والتأكد من استدعاء اسم الخدمة الصحيح
    $this->mock(CircuitBreakerService::class, function ($mock) {
        $mock->shouldReceive('canProceed')
            ->once()
            ->with('dataTypeField.indexFields') // هذا يضمن اختبار دالة circuitServiceName وتغطيتها
            ->andReturn(true);
        $mock->shouldIgnoreMissing();
    });
});

test('it retrieves and caches fields for a given data type', function () {
    // 2. تحضير الـ Mock للـ Repository
    $repo = mock(FieldRepositoryRead::class);

    // 3. تحضير كائن DataType حقيقي
    $dataType = new DataType();
    $dataType->id = 55;

    $expectedFields = [
        ['id' => 1, 'name' => 'field_1'],
        ['id' => 2, 'name' => 'field_2']
    ];

    // 4. تحديد التوقعات
    $repo->shouldReceive('list')
        ->once()
        ->with($dataType)
        ->andReturn($expectedFields);

    // 5. التنفيذ
    $action = new IndexFieldsAction($repo);
    $results = $action->execute($dataType);

    // 6. التأكيد
    expect($results)->toBe($expectedFields);
});