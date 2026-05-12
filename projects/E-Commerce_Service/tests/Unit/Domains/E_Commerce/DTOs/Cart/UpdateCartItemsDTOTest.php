<?php

use App\Domains\E_Commerce\DTOs\Cart\UpdateCartItemsDTO;
use App\Domains\E_Commerce\Requests\UpdateCartRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

it('maps update request data to dto correctly', function () {
  // 1. Arrange
  $request = new UpdateCartRequest();

  $request->merge([
    'project_id' => 200,
    'items' => [
      ['cart_item_id' => 1, 'quantity' => 10],
      ['cart_item_id' => 2, 'quantity' => 20],
    ]
  ]);

  $request->attributes = new ParameterBag([
    'auth_user' => ['id' => 55]
  ]);

  // 2. Act
  $dto = UpdateCartItemsDTO::fromRequest($request);

  // 3. Assert
  expect($dto)->toBeInstanceOf(UpdateCartItemsDTO::class)
    ->and($dto->project_id)->toBe(200)
    ->and($dto->user_id)->toBe(55)
    ->and($dto->items)->toBeArray()
    ->and($dto->items)->toHaveCount(2)
    ->and($dto->items[1]['quantity'])->toBe(20);
});

it('can be constructed manually', function () {
  $dto = new UpdateCartItemsDTO(1, 2, ['key' => 'value']);

  expect($dto->project_id)->toBe(1)
    ->and($dto->user_id)->toBe(2)
    ->and($dto->items)->toBe(['key' => 'value']);
});
