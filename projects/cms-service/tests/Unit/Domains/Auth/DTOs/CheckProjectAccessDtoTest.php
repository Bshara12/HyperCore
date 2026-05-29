<?php

use App\Domains\Auth\DTOs\CheckProjectAccessDto;

test('it initializes with all properties correctly', function () {
  $dto = new CheckProjectAccessDto(
    userId: 1,
    projectKey: 'pro_xyz_789'
  );

  expect($dto->userId)->toBe(1)
    ->and($dto->projectKey)->toBe('pro_xyz_789');
});
