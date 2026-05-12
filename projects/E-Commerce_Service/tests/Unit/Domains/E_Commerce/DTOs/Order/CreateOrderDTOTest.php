<?php

use App\Domains\E_Commerce\DTOs\Order\CreateOrderDTO;
use App\Domains\E_Commerce\Requests\CreateOrderRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

it('creates a create order dto from request correctly', function () {
  // 1. Arrange: إعداد الطلب
  $request = new CreateOrderRequest();

  $request->merge([
    'project_id' => 77,
    'cart_id' => 1024,
    'address' => [
      'country' => 'Saudi Arabia',
      'city' => 'Jeddah',
    ]
  ]);

  // محاكاة المستخدم المصرح له
  $request->attributes = new ParameterBag([
    'auth_user' => ['id' => 505]
  ]);

  // 2. Act: التنفيذ
  $dto = CreateOrderDTO::fromRequest($request);

  // 3. Assert: التحقق
  expect($dto)->toBeInstanceOf(CreateOrderDTO::class)
    ->and($dto->project_id)->toBe(77)
    ->and($dto->user_id)->toBe(505)
    ->and($dto->cart_id)->toBe(1024)
    ->and($dto->address)->toBeArray()
    ->and($dto->address['city'])->toBe('Jeddah');
});

it('can be instantiated manually via constructor', function () {
  $address = ['street' => 'Main St'];
  $dto = new CreateOrderDTO(1, 2, 3, $address);

  expect($dto->project_id)->toBe(1)
    ->and($dto->user_id)->toBe(2)
    ->and($dto->cart_id)->toBe(3)
    ->and($dto->address)->toBe($address);
});
