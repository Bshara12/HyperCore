<?php

use App\Domains\CMS\DTOs\Rate\GetRatingStatsDTO;

test('it creates DTO from request properties correctly', function () {
  // محاكاة كائن الطلب
  $request = new class {
    public $rateable_type = 'post';
    public $rateable_id = 99;
  };

  // التنفيذ
  $dto = GetRatingStatsDTO::fromRequest($request);

  // التحقق
  expect($dto->rateableType)->toBe('post')
    ->and($dto->rateableId)->toBe(99);
});
