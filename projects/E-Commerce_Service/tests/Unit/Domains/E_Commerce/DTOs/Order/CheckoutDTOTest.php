<?php

use App\Domains\E_Commerce\DTOs\Order\CheckoutDTO;
use App\Domains\E_Commerce\Requests\CheckoutRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

it('creates a checkout dto from request correctly', function () {
  // 1. Arrange: إعداد الطلب والبيانات الوهمية
  $request = new CheckoutRequest();

  $request->merge([
    'project_id' => 10,
    'cart_id' => 500,
    'payment_method' => 'card',
    'gateway' => 'stripe',
    'payment_type' => 'full',
    'address' => [
      'city' => 'Riyadh',
      'street' => 'Olaya St',
      'zip' => '12345'
    ]
  ]);

  // محاكاة بيانات المستخدم في الـ Attributes
  $request->attributes = new ParameterBag([
    'auth_user' => [
      'id' => 1,
      'name' => 'Ahmed Mohamed'
    ]
  ]);

  // 2. Act: التنفيذ
  $dto = CheckoutDTO::fromRequest($request);

  // 3. Assert: التحقق
  expect($dto)->toBeInstanceOf(CheckoutDTO::class)
    ->and($dto->project_id)->toBe(10)
    ->and($dto->user_id)->toBe(1)
    ->and($dto->user_name)->toBe('Ahmed Mohamed')
    ->and($dto->cart_id)->toBe(500)
    ->and($dto->payment_method)->toBe('card')
    ->and($dto->gateway)->toBe('stripe')
    ->and($dto->address)->toBeArray()
    ->and($dto->address['city'])->toBe('Riyadh');
});

it('handles optional fields when they are null', function () {
  // اختبار الحقول الاختيارية لضمان عدم حدوث خطأ عند غيابها
  $request = new CheckoutRequest();
  $request->merge([
    'project_id' => 10,
    'cart_id' => 500,
    'payment_method' => 'cash',
    'gateway' => null, // اختيارية
    'payment_type' => null, // اختيارية
    'address' => []
  ]);

  $request->attributes = new ParameterBag([
    'auth_user' => ['id' => 1, 'name' => 'Guest']
  ]);

  $dto = CheckoutDTO::fromRequest($request);

  expect($dto->gateway)->toBeNull()
    ->and($dto->payment_type)->toBeNull();
});

it('can be manually instantiated via constructor', function () {
  $dto = new CheckoutDTO(
    project_id: 1,
    user_id: 2,
    user_name: 'Test',
    cart_id: 3,
    payment_method: 'bank',
    gateway: 'local',
    payment_type: 'partial',
    address: ['zone' => 'A']
  );

  expect($dto->user_name)->toBe('Test')
    ->and($dto->address)->toBe(['zone' => 'A']);
});
