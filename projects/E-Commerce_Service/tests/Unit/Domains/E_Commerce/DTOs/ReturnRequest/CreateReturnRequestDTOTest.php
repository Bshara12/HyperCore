<?php

use App\Domains\E_Commerce\DTOs\ReturnRequest\CreateReturnRequestDTO;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

it('creates a return request dto from request with middleware attributes correctly', function () {
  // 1. Arrange: إعداد الطلب والبيانات
  $request = new Request();

  $request->merge([
    'order_id' => 100,
    'order_item_id' => 500,
    'description' => 'The product is damaged upon arrival.',
    'quantity' => 1,
    'project_id' => 10 // القيمة القادمة من الطلب أو الميدل وير
  ]);

  // محاكاة بيانات المستخدم من الـ Middleware
  $request->attributes = new ParameterBag([
    'auth_user' => ['id' => 25]
  ]);

  // 2. Act: التنفيذ
  $dto = CreateReturnRequestDTO::fromRequest($request);

  // 3. Assert: التحقق
  expect($dto)->toBeInstanceOf(CreateReturnRequestDTO::class)
    ->and($dto->user_id)->toBe(25)
    ->and($dto->order_id)->toBe(100)
    ->and($dto->order_item_id)->toBe(500)
    ->and($dto->description)->toBe('The product is damaged upon arrival.')
    ->and($dto->quantity)->toBe(1)
    ->and($dto->project_id)->toBe(10);
});

it('can be instantiated manually via constructor', function () {
  // اختبار الـ constructor لضمان تغطية الخصائص المضافة حديثاً
  $dto = new CreateReturnRequestDTO(
    user_id: 1,
    order_id: 2,
    order_item_id: 3,
    description: 'Test',
    quantity: 5,
    project_id: 10
  );

  expect($dto->project_id)->toBe(10)
    ->and($dto->quantity)->toBe(5);
});
