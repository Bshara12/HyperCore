<?php

namespace Tests\Unit\Domains\Booking\Analytics\DTOs;

use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use Illuminate\Http\Request;
use Tests\TestCase;

// تحديد الكلاس لرفع التغطية
covers(AnalyticsFilterDTO::class);

test('it can be instantiated with manual values', function () {
  // Arrange & Act
  $dto = new AnalyticsFilterDTO(
    from: '2026-01-01',
    to: '2026-01-31',
    period: 'monthly',
    projectId: 5,
    limit: 20
  );

  // Assert
  expect($dto->from)->toBe('2026-01-01')
    ->and($dto->to)->toBe('2026-01-31')
    ->and($dto->period)->toBe('monthly')
    ->and($dto->projectId)->toBe(5)
    ->and($dto->limit)->toBe(20);
});

test('it creates dto from request with provided values', function () {
  // Arrange
  $request = new Request([
    'from' => '2026-05-01',
    'to' => '2026-05-07',
    'period' => 'weekly',
    'project_id' => 10,
    'limit' => '15'
  ]);

  // Act
  $dto = AnalyticsFilterDTO::fromRequest($request);

  // Assert
  expect($dto->from)->toBe('2026-05-01')
    ->and($dto->to)->toBe('2026-05-07')
    ->and($dto->period)->toBe('weekly')
    ->and($dto->projectId)->toBe(10)
    ->and($dto->limit)->toBe(15);
});

test('it uses default values when request is empty', function () {
  // Arrange
  $request = new Request(['project_id' => 1]); // project_id مطلوب

  // Act
  $dto = AnalyticsFilterDTO::fromRequest($request);

  // Assert
  expect($dto->from)->toBe(now()->subMonth()->format('Y-m-d'))
    ->and($dto->to)->toBe(now()->format('Y-m-d'))
    ->and($dto->period)->toBe('daily')
    ->and($dto->limit)->toBe(10);
});

test('it falls back to daily if an invalid period is provided', function () {
  // Arrange
  $request = new Request([
    'project_id' => 1,
    'period' => 'yearly' // قيمة غير موجودة في الـ array المسموح به
  ]);

  // Act
  $dto = AnalyticsFilterDTO::fromRequest($request);

  // Assert
  expect($dto->period)->toBe('daily');
});

test('it casts limit to integer correctly', function () {
  // Arrange
  $request = new Request([
    'project_id' => 1,
    'limit' => '25' // مرسل كـ string
  ]);

  // Act
  $dto = AnalyticsFilterDTO::fromRequest($request);

  // Assert
  expect($dto->limit)->toBe(25)
    ->and($dto->limit)->toBeInt();
});
