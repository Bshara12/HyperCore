<?php

use App\Domains\E_Commerce\DTOs\Cart\RemoveCartItemsDTO;
use App\Domains\E_Commerce\Requests\RemoveCartItemsRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

it('transforms request data into remove cart items dto correctly', function () {
  // 1. Arrange: تجهيز الـ Request والبيانات
  $request = new RemoveCartItemsRequest();

  // محاكاة البيانات القادمة من المستخدم (مصفوفة تحتوي على مفتاح item_id)
  $request->merge([
    'project_id' => 50,
    'items' => [
      ['item_id' => 101],
      ['item_id' => 102],
      ['item_id' => 103],
    ]
  ]);

  // محاكاة السمة auth_user التي يضيفها الـ Middleware
  $request->attributes = new ParameterBag([
    'auth_user' => ['id' => 7]
  ]);

  // 2. Act: التنفيذ
  $dto = RemoveCartItemsDTO::fromRequest($request);

  // 3. Assert: التحقق
  expect($dto)->toBeInstanceOf(RemoveCartItemsDTO::class)
    ->and($dto->project_id)->toBe(50)
    ->and($dto->user_id)->toBe(7)
    ->and($dto->item_ids)->toBeArray()
    ->and($dto->item_ids)->toEqual([101, 102, 103]); // التحقق من نجاح عملية الـ pluck
});

it('can be created manually using constructor', function () {
  // اختبار الـ constructor مباشرة لضمان تغطية 100%
  $ids = [1, 2, 3];
  $dto = new RemoveCartItemsDTO(project_id: 1, user_id: 2, item_ids: $ids);

  expect($dto->project_id)->toBe(1)
    ->and($dto->user_id)->toBe(2)
    ->and($dto->item_ids)->toBe($ids);
});
