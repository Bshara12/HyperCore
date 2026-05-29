<?php

namespace Tests\Unit\Domains\CMS\Actions\Rate;

use App\Domains\CMS\Actions\Rate\GetRatingStatsAction;
use App\Domains\CMS\DTOs\Rate\GetRatingStatsDTO;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Mockery;

afterEach(function () {
  Mockery::close();
});

test('it retrieves stats for project type', function () {
  // 1. التجهيز
  $dto = new GetRatingStatsDTO('project', 123);
  $expectedStats = ['average' => 4.5, 'count' => 10];

  $projectRepo = Mockery::mock(ProjectRepositoryInterface::class);
  $dataRepo = Mockery::mock(DataEntryRepositoryInterface::class);

  // 2. تزييف الكاش والتأكد من استدعاء الريبوزيتوري الصحيح
  Cache::shouldReceive('remember')
    ->once()
    ->with(CacheKeys::ratingStats('project', 123), CacheKeys::TTL_MEDIUM, Mockery::type('callable'))
    ->andReturnUsing(fn($key, $ttl, $callback) => $callback());

  $projectRepo->shouldReceive('getRatingStats')
    ->once()
    ->with(123)
    ->andReturn($expectedStats);

  // 3. التنفيذ
  $action = new GetRatingStatsAction($projectRepo, $dataRepo);
  $result = $action->execute($dto);

  // 4. التأكيد
  expect($result)->toBe($expectedStats);
});

test('it retrieves stats for data type', function () {
  // 1. التجهيز
  $dto = new GetRatingStatsDTO('data', 99);
  $expectedStats = ['average' => 3.0, 'count' => 5];

  $projectRepo = Mockery::mock(ProjectRepositoryInterface::class);
  $dataRepo = Mockery::mock(DataEntryRepositoryInterface::class);

  Cache::shouldReceive('remember')
    ->once()
    ->with(CacheKeys::ratingStats('data', 99), CacheKeys::TTL_MEDIUM, Mockery::type('callable'))
    ->andReturnUsing(fn($key, $ttl, $callback) => $callback());

  $dataRepo->shouldReceive('getRatingStats')
    ->once()
    ->with(99)
    ->andReturn($expectedStats);

  $action = new GetRatingStatsAction($projectRepo, $dataRepo);
  $result = $action->execute($dto);

  expect($result)->toBe($expectedStats);
});

test('it throws exception for unsupported type', function () {
  $dto = new GetRatingStatsDTO('invalid_type', 1);

  $projectRepo = Mockery::mock(ProjectRepositoryInterface::class);
  $dataRepo = Mockery::mock(DataEntryRepositoryInterface::class);

  // نتوقع أن يرمي استثناء عند تنفيذ الـ callback الخاص بـ remember
  // أو مباشرة عند محاولة الوصول للـ match
  Cache::shouldReceive('remember')->andReturnUsing(fn($k, $t, $cb) => $cb());

  expect(fn() => (new GetRatingStatsAction($projectRepo, $dataRepo))->execute($dto))
    ->toThrow(\InvalidArgumentException::class, "Unsupported rateable type: invalid_type");
});
