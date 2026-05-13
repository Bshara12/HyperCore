<?php

use App\Domains\E_Commerce\DTOs\Offers\SubscribeDTO;
use App\Domains\E_Commerce\Requests\SubscribeOfferRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

it('maps request and attribute data to subscribe dto correctly', function () {
  // 1. Arrange: تجهيز البيانات المتفرقة
  $slug = 'vip-discount-2026';
  $request = new SubscribeOfferRequest();

  // بيانات من الـ Body
  $request->merge([
    'code' => 'SUMMER20',
    'project_id' => 450
  ]);

  // بيانات من الـ Middleware (Attributes)
  $request->attributes = new ParameterBag([
    'auth_user' => ['id' => 101]
  ]);

  // 2. Act: التنفيذ
  $dto = SubscribeDTO::fromRequest($slug, $request);

  // 3. Assert: التحقق من التجميع
  expect($dto)->toBeInstanceOf(SubscribeDTO::class)
    ->and($dto->collectionSlug)->toBe($slug)
    ->and($dto->code)->toBe('SUMMER20')
    ->and($dto->user_id)->toBe(101)
    ->and($dto->project_id)->toBe(450);
});

it('can be instantiated manually via constructor', function () {
  // اختبار الـ constructor لضمان تغطية الخصائص
  $dto = new SubscribeDTO('slug', 'code123', 1, 2);

  expect($dto->collectionSlug)->toBe('slug')
    ->and($dto->code)->toBe('code123')
    ->and($dto->user_id)->toBe(1)
    ->and($dto->project_id)->toBe(2);
});
