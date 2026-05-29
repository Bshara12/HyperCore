<?php

use App\Domains\CMS\DTOs\AI\GenerateProjectFromSchemaDTO;
use App\Domains\CMS\Requests\AIGenerateSchemaRequest;

test('it creates a dto instance from request with all data successfully', function () {
  // 1. إنشاء الـ Request وتعبئته ببيانات كاملة ومثالية
  $request = AIGenerateSchemaRequest::create('/mock-endpoint', 'POST', [
    'project_info' => ['name' => 'My Awesome Project', 'description' => 'AI Generated'],
    'custom_data_types' => ['articles', 'categories'],
    'relations' => ['articles_belong_to_categories'],
  ]);

  // محاكاة وجود المستخدم المصرح له داخل الـ attributes (مثل الـ Middleware)
  $request->attributes->set('auth_user', ['id' => 101]);

  // 2. تنفيذ الدالة من الـ DTO
  $dto = GenerateProjectFromSchemaDTO::fromRequest($request);

  // 3. التأكد من تطابق البيانات المسندة بنجاح داخل الـ DTO
  expect($dto->ownerId)->toBe(101)
    ->and($dto->projectInfo)->toBe(['name' => 'My Awesome Project', 'description' => 'AI Generated'])
    ->and($dto->customDataTypes)->toBe(['articles', 'categories'])
    ->and($dto->relations)->toBe(['articles_belong_to_categories']);
});

test('it creates a dto instance with empty arrays when optional data is missing', function () {
  // 1. إنشاء الـ Request بالبيانات الإجبارية فقط واختبار الـ (?? [])
  $request = AIGenerateSchemaRequest::create('/mock-endpoint', 'POST', [
    'project_info' => ['name' => 'Minimal Project'],
    // ترك custom_data_types و relations فارغين تماماً
  ]);

  $request->attributes->set('auth_user', ['id' => 202]);

  // 2. تنفيذ الدالة
  $dto = GenerateProjectFromSchemaDTO::fromRequest($request);

  // 3. التأكد من أن الحقول الاختيارية أخذت قيم مصفوفات فارغة افتراضية
  expect($dto->ownerId)->toBe(202)
    ->and($dto->projectInfo)->toBe(['name' => 'Minimal Project'])
    ->and($dto->customDataTypes)->toBe([]) // مصفوفة فارغة بفضل الـ ?? []
    ->and($dto->relations)->toBe([]);    // مصفوفة فارغة بفضل الـ ?? []
});
