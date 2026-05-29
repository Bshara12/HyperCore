<?php

use App\Domains\CMS\DTOs\Rate\GetRatingsDTO;

test('it creates DTO from request with provided values', function () {
  // إنشاء كائن بسيط يحاكي الـ Request للوصول للخصائص المباشرة وللميثود get
  $request = new class {
    public $rateable_type = 'product';
    public $rateable_id = 101;

    public function get($key, $default = null)
    {
      return $key === 'per_page' ? 50 : $default;
    }
  };

  $dto = GetRatingsDTO::fromRequest($request);

  expect($dto->rateableType)->toBe('product')
    ->and($dto->rateableId)->toBe(101)
    ->and($dto->perPage)->toBe(50);
});

test('it uses default perPage value when missing in request', function () {
  $request = new class {
    public $rateable_type = 'article';
    public $rateable_id = 202;

    public function get($key, $default = null)
    {
      return $default; // يحاكي عدم وجود per_page
    }
  };

  $dto = GetRatingsDTO::fromRequest($request);

  expect($dto->perPage)->toBe(10); // القيمة الافتراضية
});
