<?php

use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;
use App\Domains\Subscription\Requests\Rule\CreateFeatureRuleRequest;

test('it initializes with all properties correctly via constructor', function () {
  $dto = new CreateFeatureRuleDTO(
    projectId: 1,
    eventKey: 'user.login',
    featureKey: 'dashboard',
    action: 'allow',
    resetType: 'monthly',
    isActive: true,
    metadata: ['limit' => 10]
  );

  expect($dto->projectId)->toBe(1)
    ->and($dto->eventKey)->toBe('user.login')
    ->and($dto->isActive)->toBeTrue();
});

test('it creates a DTO from CreateFeatureRuleRequest correctly', function () {
  // 1. إنشاء Mock للـ Request
  $request = Mockery::mock(CreateFeatureRuleRequest::class);

  // تعيين الخصائص التي سيقرأها الـ DTO
  $request->project_id = 10;
  $request->event_key = 'api.access';
  $request->feature_key = 'premium_export';
  $request->action = 'grant';
  $request->reset_type = 'daily';
  $request->metadata = ['plan' => 'gold'];

  // محاكاة ميثود الـ boolean الخاص بـ Laravel
  $request->shouldReceive('boolean')
    ->once()
    ->with('is_active', true)
    ->andReturn(true);

  // 2. استدعاء الـ Factory Method
  $dto = CreateFeatureRuleDTO::fromRequest($request);

  // 3. التحقق
  expect($dto->projectId)->toBe(10)
    ->and($dto->eventKey)->toBe('api.access')
    ->and($dto->isActive)->toBeTrue()
    ->and($dto->metadata)->toBe(['plan' => 'gold']);
});
