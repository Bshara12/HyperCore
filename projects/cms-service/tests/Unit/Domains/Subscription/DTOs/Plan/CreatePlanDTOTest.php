<?php

use App\Domains\Subscription\DTOs\Plan\CreatePlanDTO;
use App\Domains\Subscription\Requests\Plan\CreatePlanRequest;

test('it initializes with all properties correctly', function () {
  $dto = new CreatePlanDTO(
    projectId: 1,
    name: 'Pro Plan',
    slug: 'pro-plan',
    description: 'Best plan',
    price: 99.99,
    currency: 'USD',
    durationDays: 30,
    isActive: true,
    features: ['feature1'],
    metadata: ['key' => 'value']
  );

  expect($dto->name)->toBe('Pro Plan')
    ->and($dto->price)->toBe(99.99)
    ->and($dto->durationDays)->toBe(30)
    ->and($dto->isActive)->toBeTrue();
});

test('it creates a DTO from CreatePlanRequest with proper type casting', function () {
  // 1. محاكاة الـ Request
  $request = Mockery::mock(CreatePlanRequest::class);

  // تعيين قيم (باعتبارها قادمة من HTTP Request كـ String مثلاً)
  $request->project_id = 10;
  $request->name = 'Basic';
  $request->slug = 'basic';
  $request->description = 'Simple plan';
  $request->price = '49.50';        // سيتم تحويله لـ float
  $request->currency = 'EUR';
  $request->duration_days = '365';  // سيتم تحويله لـ int
  $request->features = ['chat', 'email'];
  $request->metadata = null;

  // محاكاة ميثود الـ boolean
  $request->shouldReceive('boolean')
    ->once()
    ->with('is_active', true)
    ->andReturn(true);

  // 2. استدعاء الـ Factory Method
  $dto = CreatePlanDTO::fromRequest($request);

  // 3. التحقق من القيم والأنواع (Type Casting)
  expect($dto->price)->toBe(49.50)
    ->and($dto->price)->toBeFloat()
    ->and($dto->durationDays)->toBe(365)
    ->and($dto->durationDays)->toBeInt()
    ->and($dto->isActive)->toBeTrue()
    ->and($dto->features)->toBe(['chat', 'email']);
});
