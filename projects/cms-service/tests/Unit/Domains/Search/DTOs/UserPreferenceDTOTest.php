<?php

use App\Domains\Search\DTOs\UserPreferenceDTO;

test('it initializes with provided values correctly', function () {
  $typeScores = ['product' => 0.8, 'article' => 0.2];

  $dto = new UserPreferenceDTO(
    preferredType: 'product',
    confidence: 0.85,
    typeScores: $typeScores,
    totalClicks: 50,
    hasHistory: true
  );

  expect($dto->preferredType)->toBe('product')
    ->and($dto->confidence)->toBe(0.85)
    ->and($dto->typeScores)->toBe($typeScores)
    ->and($dto->totalClicks)->toBe(50)
    ->and($dto->hasHistory)->toBeTrue();
});

test('it creates a default noHistory instance correctly', function () {
  $dto = UserPreferenceDTO::noHistory();

  expect($dto->preferredType)->toBe('general')
    ->and($dto->confidence)->toBe(0.0)
    ->and($dto->typeScores)->toBeEmpty()
    ->and($dto->totalClicks)->toBe(0)
    ->and($dto->hasHistory)->toBeFalse();
});
