<?php

use App\Domains\E_Commerce\DTOs\Order\UpdateOrderStatusDTO;
use Illuminate\Http\Request;

it('creates an update order status dto from request and id correctly', function () {
  // 1. Arrange
  $orderId = 999;
  $request = new Request();
  $request->merge([
    'project_id' => 5,
    'status' => 'shipped'
  ]);

  // 2. Act
  $dto = UpdateOrderStatusDTO::fromRequest($request, $orderId);

  // 3. Assert
  expect($dto)->toBeInstanceOf(UpdateOrderStatusDTO::class)
    ->and($dto->order_id)->toBe(999)
    ->and($dto->project_id)->toBe(5)
    ->and($dto->status)->toBe('shipped');
});

it('can be instantiated manually via constructor', function () {
  $dto = new UpdateOrderStatusDTO(order_id: 1, project_id: 2, status: 'delivered');

  expect($dto->order_id)->toBe(1)
    ->and($dto->project_id)->toBe(2)
    ->and($dto->status)->toBe('delivered');
});
