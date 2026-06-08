<?php

use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Requests\UpdateDataTypeRequest;

test('it maps request data to DTO properties correctly', function () {
  // 1. إنشاء نسخة من الـ Request ودمج البيانات
  $request = new UpdateDataTypeRequest();
  $request->merge([
    'name' => 'Updated Name',
    'slug' => 'updated-slug',
    'description' => 'New Description',
    'is_active' => '0', // اختبار الـ boolean casting
    'settings' => ['theme' => 'dark'],
  ]);

  // 2. التنفيذ
  $dto = UpdateDataTypeDTO::fromRequest($request);

  // 3. التحقق
  expect($dto->name)->toBe('Updated Name')
    ->and($dto->slug)->toBe('updated-slug')
    ->and($dto->description)->toBe('New Description')
    ->and($dto->is_active)->toBeFalse() // التأكد من أن '0' تحولت لـ false
    ->and($dto->settings)->toBe(['theme' => 'dark']);
});

test('it uses default values when optional fields are missing', function () {
  $request = new UpdateDataTypeRequest();
  $request->merge([
    'name' => 'Name',
    'slug' => 'slug',
  ]);

  $dto = UpdateDataTypeDTO::fromRequest($request);

  // التأكد من أن القيم الافتراضية تعمل (is_active=true و settings=[])
  expect($dto->is_active)->toBeTrue()
    ->and($dto->settings)->toBeEmpty();
});
