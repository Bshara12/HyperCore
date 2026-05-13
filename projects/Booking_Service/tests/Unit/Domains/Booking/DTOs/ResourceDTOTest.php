<?php

use App\Domains\Booking\DTOs\ResourceDTO;
use App\Domains\Booking\Requests\CreateResourceRequest;
use App\Domains\Booking\Requests\UpdateResourceRequest;
use App\Models\Resource;

test('it can be created from create request with paid payment type', function () {
  // 1. Arrange
  $request = new CreateResourceRequest([
    'project_id' => 1,
    'data_entry_id' => 10,
    'name' => 'Meeting Room',
    'type' => 'room',
    'capacity' => 5,
    'payment_type' => 'paid',
    'price' => 150.0,
    'settings' => ['wifi' => true]
  ]);

  // 2. Act
  $dto = ResourceDTO::fromCreateRequest($request);

  // 3. Assert
  expect($dto->price)->toBe(150.0)
    ->and($dto->capacity)->toBe(5)
    ->and($dto->paymentType)->toBe('paid');
});

test('it sets price to null if payment type is free in fromCreateRequest', function () {
  $request = new CreateResourceRequest([
    'payment_type' => 'free',
    'price' => 100.0 // سيتم تجاهله
  ]);

  $dto = ResourceDTO::fromCreateRequest($request);

  expect($dto->price)->toBeNull();
});

test('it can be created from update request', function () {
  // 1. Arrange
  $data = [
    'name' => 'Updated Name',
    'type' => 'hall',
    'status' => 'inactive',
  ];

  /** @var UpdateResourceRequest|\Mockery\MockInterface $request */
  $request = Mockery::mock(UpdateResourceRequest::class);

  // محاكاة دالة validated()
  $request->shouldReceive('validated')->andReturn($data);

  // محاكاة دالة all() كاحتياط
  $request->shouldReceive('all')->andReturn($data);

  // حل مشكلة has(): نخبر الـ Mock أن يرد بـ true إذا كان الحقل موجوداً في مصفوفتنا
  $request->shouldReceive('has')->andReturnUsing(function ($key) use ($data) {
    return array_key_exists($key, $data);
  });

  // محاكاة الوصول المباشر للخصائص (Magic Methods)
  $request->shouldReceive('__get')->andReturnUsing(function ($key) use ($data) {
    return $data[$key] ?? null;
  });

  // 2. Act
  $dto = ResourceDTO::fromUpdateRequest($request);

  // 3. Assert
  expect($dto->name)->toBe('Updated Name')
    ->and($dto->type)->toBe('hall')
    ->and($dto->status)->toBe('inactive')
    ->and($dto->capacity)->toBeNull();
});

test('toCreateArray returns correct data with defaults', function () {
  $dto = new ResourceDTO(
    name: 'Simple Resource',
    paymentType: Resource::PAYMENT_FREE
  );

  $array = $dto->toCreateArray();

  expect($array['capacity'])->toBe(1) // القيمة الافتراضية
    ->and($array['status'])->toBe(Resource::STATUS_ACTIVE)
    ->and($array['price'])->toBeNull();
});

test('toUpdateArray filters null values but keeps price null for free payment', function () {
  $dto = new ResourceDTO(
    name: 'New Name',
    paymentType: Resource::PAYMENT_FREE
  );

  $array = $dto->toUpdateArray();

  expect($array)->toHaveKey('name', 'New Name')
    ->and($array)->toHaveKey('price', null)
    ->and($array)->not->toHaveKey('capacity'); // محذوف لأنه null
});

test('toUpdateArray includes price for paid payment type', function () {
  $dto = new ResourceDTO(
    paymentType: Resource::PAYMENT_PAID,
    price: 250.0
  );

  $array = $dto->toUpdateArray();

  expect($array['price'])->toBe(250.0);
});
