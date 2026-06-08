<?php

use App\Domains\Search\DTOs\PopularSearchQueryDTO;

test('it correctly initializes all properties', function () {
  $dto = new PopularSearchQueryDTO(
    projectId: 10,
    language: 'ar',
    window: '30d',
    type: 'trending',
    limit: 25
  );

  expect($dto->projectId)->toBe(10)
    ->and($dto->language)->toBe('ar')
    ->and($dto->window)->toBe('30d')
    ->and($dto->type)->toBe('trending')
    ->and($dto->limit)->toBe(25);
});
