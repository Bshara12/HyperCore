<?php

namespace Tests\Unit\Domains\Booking\Analytics\DTOs;

use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\Booking\Analytics\Requests\AnalyticsFilterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

/*
|--------------------------------------------------------------------------
| Regressions: the project came from the caller, and limit was unbounded
|--------------------------------------------------------------------------
*/

test('it takes the project from the resolved project, not from the query string', function () {
    // ResolveProject merges the resolved project into the request. A caller who
    // also passes ?project_id=999 must not be able to override it — that is how
    // anonymous cross-tenant reads happened on this endpoint.
    $request = Request::create('/api/booking/analytics/overview', 'GET', [
        'project_id' => 999,
    ]);

    $request->merge(['project' => ['id' => 7], 'project_id' => 7]);

    $dto = AnalyticsFilterDTO::fromRequest($request);

    expect($dto->projectId)->toBe(7);
});

test('it refuses to run when no project was resolved', function () {
    $request = Request::create('/api/booking/analytics/overview', 'GET');

    expect(fn () => AnalyticsFilterDTO::fromRequest($request))
        ->toThrow(HttpException::class);
});

test('it caps the limit', function () {
    $request = Request::create('/api/booking/analytics/overview', 'GET', [
        'limit' => 999999999,
    ]);

    $request->merge(['project' => ['id' => 7]]);

    $dto = AnalyticsFilterDTO::fromRequest($request);

    expect($dto->limit)->toBe(AnalyticsFilterDTO::MAX_LIMIT);
});

test('the filter request rejects malformed dates, inverted ranges and oversized limits', function ($payload, $invalidKey) {
    $validator = Validator::make($payload, (new AnalyticsFilterRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has($invalidKey))->toBeTrue();
})->with([
    'garbage from' => [['from' => 'NOT-A-DATE'], 'from'],
    'inverted range' => [['from' => '2030-01-01', 'to' => '2020-01-01'], 'to'],
    'oversized limit' => [['limit' => 999999999], 'limit'],
    'unknown period' => [['period' => 'hourly'], 'period'],
]);
