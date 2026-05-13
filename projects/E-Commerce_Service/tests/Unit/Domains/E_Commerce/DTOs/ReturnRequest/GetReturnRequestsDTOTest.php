<?php

use App\Domains\E_Commerce\DTOs\ReturnRequest\GetReturnRequestsDTO;
use Illuminate\Http\Request;

it('creates a get return requests dto from request correctly', function () {
  // 1. Arrange: إعداد الطلب ومحاكاة وجود الـ project_id
  $request = new Request();
  $request->merge([
    'project_id' => 42
  ]);

  // 2. Act: التنفيذ
  $dto = GetReturnRequestsDTO::fromRequest($request);

  // 3. Assert: التحقق
  expect($dto)->toBeInstanceOf(GetReturnRequestsDTO::class)
    ->and($dto->project_id)->toBe(42);
});

it('can be instantiated manually via constructor', function () {
  // اختبار الـ constructor لضمان تغطية الخصائص
  $dto = new GetReturnRequestsDTO(project_id: 100);

  expect($dto->project_id)->toBe(100);
});
