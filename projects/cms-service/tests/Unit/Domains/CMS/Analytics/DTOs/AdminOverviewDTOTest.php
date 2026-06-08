<?php

use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use Illuminate\Http\Request;

test('it maps provided values correctly from request', function () {
  $request = new Request([
    'from' => '2026-01-01',
    'to' => '2026-01-31',
    'period' => 'weekly'
  ]);

  $dto = AdminOverviewDTO::fromRequest($request);

  expect($dto->from)->toBe('2026-01-01')
    ->and($dto->to)->toBe('2026-01-31')
    ->and($dto->period)->toBe('weekly');
});

test('it uses default values when inputs are missing', function () {
  $request = new Request([]); // طلب فارغ

  $dto = AdminOverviewDTO::fromRequest($request);

  // نتحقق من أن التواريخ تُرجع بصيغة Y-m-d (Regex لضمان التنسيق)
  expect($dto->from)->toMatch('/^\d{4}-\d{2}-\d{2}$/')
    ->and($dto->to)->toMatch('/^\d{4}-\d{2}-\d{2}$/')
    ->and($dto->period)->toBe('daily'); // الافتراضي
});

test('it falls back to daily period when an invalid period is provided', function () {
  $request = new Request(['period' => 'yearly']); // قيمة غير مسموح بها

  $dto = AdminOverviewDTO::fromRequest($request);

  expect($dto->period)->toBe('daily');
});
