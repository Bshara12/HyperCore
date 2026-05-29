<?php

use App\Domains\Subscription\DTOs\ContentAccess\ActivateContentAccessDTO;
use App\Models\ContentAccessMetadata;

test('it initializes with the provided content access metadata', function () {
  // 1. محاكاة (Mock) للـ Model الخاص بـ ContentAccessMetadata
  $metadata = Mockery::mock(ContentAccessMetadata::class);

  // 2. إنشاء الـ DTO وتمرير الـ Mock
  $dto = new ActivateContentAccessDTO(
    contentAccessMetadata: $metadata
  );

  // 3. التحقق من أن الكائن المخزن هو نفسه الذي مررناه
  expect($dto->contentAccessMetadata)->toBe($metadata)
    ->and($dto->contentAccessMetadata)->toBeInstanceOf(ContentAccessMetadata::class);
});
