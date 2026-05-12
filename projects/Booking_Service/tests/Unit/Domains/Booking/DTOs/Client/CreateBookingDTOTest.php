<?php

namespace Tests\Unit\Domains\Booking\DTOs\Client;

use App\Domains\Booking\DTOs\Client\CreateBookingDTO;
use Illuminate\Http\Request;

test('it can be created from request with authenticated user and full data', function () {
  // 1. Arrange: إنشاء Request حقيقي بدلاً من Mock
  $request = new Request([
    'resource_id' => 10,
    'project_id' => 5,
    'start_at' => '2026-05-10 10:00:00',
    'end_at' => '2026-05-10 11:00:00',
    'amount' => 150.50,
    'currency' => 'USD',
    'gateway' => 'stripe',
    'token' => 'tok_123'
  ]);

  // إضافة بيانات المستخدم للـ attributes (كما يفعل الـ Middleware)
  $request->attributes->set('auth_user', [
    'id' => 1,
    'name' => 'Ahmed'
  ]);

  // 2. Act
  $dto = CreateBookingDTO::fromRequest($request);

  // 3. Assert
  expect($dto->resourceId)->toBe(10)
    ->and($dto->userId)->toBe(1)
    ->and($dto->userName)->toBe('Ahmed')
    ->and($dto->projectId)->toBe(5)
    ->and($dto->amount)->toBe(150.50)
    ->and($dto->gatewayToken)->toBe('tok_123');
});

test('it uses default project_id if not provided', function () {
  // 1. Arrange: Request بدون project_id
  $request = new Request([
    'resource_id' => 10,
    'start_at' => '2026-05-10 10:00:00',
    'end_at' => '2026-05-10 11:00:00',
    'amount' => 100,
    'currency' => 'USD',
    'gateway' => 'paypal'
  ]);

  $request->attributes->set('auth_user', ['id' => 1, 'name' => 'Ahmed']);

  // 2. Act
  $dto = CreateBookingDTO::fromRequest($request);

  // 3. Assert
  expect($dto->projectId)->toBe(1); // القيمة الافتراضية في الـ DTO
});

test('it throws unauthenticated exception', function () {
  // Arrange: Request بدون مستخدم في الـ attributes
  $request = new Request();

  // Act & Assert
  expect(fn() => CreateBookingDTO::fromRequest($request))
    ->toThrow(\Exception::class, 'Unauthenticated');
});
