<?php

use App\Domains\E_Commerce\DTOs\Cart\AddCartItemsDTO;
use App\Domains\E_Commerce\Requests\CreateCartRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

it('can create a dto from a request correctly', function () {
  // 1. Preparation (Arrange)
  // نقوم بإنشاء Request وهمي وتعبئة البيانات بداخله
  $request = new CreateCartRequest();

  // محاكاة البيانات القادمة في الـ Request body
  $request->merge([
    'project_id' => 123,
    'items' => [
      ['product_id' => 1, 'quantity' => 2],
      ['product_id' => 2, 'quantity' => 5],
    ]
  ]);

  // محاكاة الـ attributes (التي يضعها الـ Middleware عادةً)
  // الـ Request في لارافيل يستخدم ParameterBag لتخزين الـ attributes
  $request->attributes = new ParameterBag([
    'auth_user' => ['id' => 99]
  ]);

  // 2. Execution (Act)
  $dto = AddCartItemsDTO::fromRequest($request);

  // 3. Assertion (Assert)
  expect($dto)->toBeInstanceOf(AddCartItemsDTO::class)
    ->and($dto->project_id)->toBe(123)
    ->and($dto->user_id)->toBe(99)
    ->and($dto->items)->toBeArray()
    ->and($dto->items)->toHaveCount(2)
    ->and($dto->items[0]['product_id'])->toBe(1);
});

it('can be instantiated manually via constructor', function () {
  // اختبار الـ constructor العادي لضمان تغطية 100%
  $items = [['id' => 1]];
  $dto = new AddCartItemsDTO(project_id: 1, user_id: 2, items: $items);

  expect($dto->project_id)->toBe(1)
    ->and($dto->user_id)->toBe(2)
    ->and($dto->items)->toBe($items);
});
