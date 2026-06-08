<?php

use App\Domains\Subscription\DTOs\Authorization\AuthorizeContentDTO;

test('it initializes with all properties correctly', function () {
  $dto = new AuthorizeContentDTO(
    userId: 1,
    projectId: 10,
    contentType: 'article',
    contentId: 500
  );

  expect($dto->userId)->toBe(1)
    ->and($dto->projectId)->toBe(10)
    ->and($dto->contentType)->toBe('article')
    ->and($dto->contentId)->toBe(500);
});

test('it handles null values for userId and projectId', function () {
  // حالة وصول مجهول (Guest) أو فحص عام
  $dto = new AuthorizeContentDTO(
    userId: null,
    projectId: null,
    contentType: 'video',
    contentId: 99
  );

  expect($dto->userId)->toBeNull()
    ->and($dto->projectId)->toBeNull()
    ->and($dto->contentType)->toBe('video');
});
