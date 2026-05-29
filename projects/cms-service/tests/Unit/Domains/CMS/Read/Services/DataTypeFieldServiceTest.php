<?php

namespace Tests\Unit\Domains\CMS\Read\Services;

use App\Domains\CMS\Read\Actions\Field\IndexFieldsAction;
use App\Domains\CMS\Read\Actions\Field\IndexTrashedFields;
use App\Domains\CMS\Read\Services\DataTypeFieldService;
use App\Models\DataType;
use Mockery;

beforeEach(function () {
    // 1. إنشاء الـ Mocks للأكشنات
    $this->indexFieldsAction = Mockery::mock(IndexFieldsAction::class);
    $this->indexTrashedFieldsAction = Mockery::mock(IndexTrashedFields::class);

    // 2. حقن الـ Mocks في الـ Service
    $this->service = new DataTypeFieldService(
        $this->indexFieldsAction,
        $this->indexTrashedFieldsAction
    );

    // 3. الحل: بدلاً من استخدام Factory الذي يحاول إنشاء مستخدم، 
    // نقوم بعمل Mock لكائن DataType مباشرة. 
    // هذا لا يتطلب قاعدة بيانات ولن يسبب خطأ الـ User.
    $this->dataType = Mockery::mock(DataType::class);
});

afterEach(function () {
  Mockery::close();
});


## 1. اختبار دالة list

test('it calls IndexFieldsAction and returns its result', function () {
  // بيانات وهمية يتوقع الأكشن إرجاعها
  $expectedResult = ['field1', 'field2'];

  // تحديد التوقعات (Expectations)
  $this->indexFieldsAction->shouldReceive('execute')
    ->once() // التأكد من استدعاء الدالة مرة واحدة
    ->with($this->dataType) // التأكد من تمرير الـ DataType الصحيح
    ->andReturn($expectedResult);

  // التنفيذ
  $result = $this->service->list($this->dataType);

  // التأكيد
  expect($result)->toBe($expectedResult);
});


## 2. اختبار دالة trashed

test('it calls IndexTrashedFields and returns its result', function () {
  // بيانات وهمية يتوقع الأكشن إرجاعها
  $expectedResult = ['trashed_field1'];

  // تحديد التوقعات (Expectations)
  $this->indexTrashedFieldsAction->shouldReceive('execute')
    ->once()
    ->with($this->dataType)
    ->andReturn($expectedResult);

  // التنفيذ
  $result = $this->service->trashed($this->dataType);

  // التأكيد
  expect($result)->toBe($expectedResult);
});
