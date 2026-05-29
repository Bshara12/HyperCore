<?php

use App\Domains\Subscription\DTOs\Subscription\CheckFeatureAccessDTO;

test('it initializes with all properties correctly', function () {
  $dto = new CheckFeatureAccessDTO(
    userId: 1,
    projectId: 50,
    featureKey: 'dashboard.export'
  );

  expect($dto->userId)->toBe(1)
    ->and($dto->projectId)->toBe(50)
    ->and($dto->featureKey)->toBe('dashboard.export');
});

test('it handles null projectId for global feature checks', function () {
  // اختبار التحقق من ميزة عامة (بدون ربط بمشروع محدد)
  $dto = new CheckFeatureAccessDTO(
    userId: 1,
    projectId: null,
    featureKey: 'system.global_access'
  );

  expect($dto->userId)->toBe(1)
    ->and($dto->projectId)->toBeNull()
    ->and($dto->featureKey)->toBe('system.global_access');
});
