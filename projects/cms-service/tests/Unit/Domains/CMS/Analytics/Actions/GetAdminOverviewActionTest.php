<?php

namespace Tests\Feature\Domains\CMS\Analytics\Actions;

use App\Domains\CMS\Analytics\Actions\GetAdminOverviewAction;
use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;

beforeEach(function () {
  // 1. عمل Mock للـ Repository Interface
  $this->repositoryMock = Mockery::mock(AnalyticsRepositoryInterface::class);

  // 2. إنشاء نسخة من الـ Action وحقن الـ Mock بداخلها
  $this->action = new GetAdminOverviewAction($this->repositoryMock);
});

afterEach(function () {
  Mockery::close();
});

// 🧠 --- قسم اختبار الـ GetAdminOverviewAction وعمل الكاش ---

test('it returns data from repository and caches it on cache miss, then hits cache next time', function () {
  $dto = new AdminOverviewDTO(from: '2026-01-01', to: '2026-01-31', period: 'daily');
  $expectedData = ['total_views' => 1500, 'revenue' => 5000];
  $cacheKey = "analytics:admin:overview:2026-01-01:2026-01-31";

  // التأكد من أن الكاش فارغ في البداية
  Cache::forget($cacheKey);

  // نحدد أن الـ Repository يجب أن يُستدعى "مرة واحدة فقط" (once)
  $this->repositoryMock->shouldReceive('getAdminOverview')
    ->once()
    ->with('2026-01-01', '2026-01-31')
    ->andReturn($expectedData);

  // الاستدعاء الأول: (Cache Miss) -> سيذهب للمستودع ويخزن في الكاش
  $firstResult = $this->action->execute($dto);
  expect($firstResult)->toBe($expectedData);

  // التأكد من أن البيانات تم حفظها فعلاً في الكاش
  expect(Cache::has($cacheKey))->toBeTrue();

  // الاستدعاء الثاني: (Cache Hit) -> سيعود من الكاش مباشرة ولن يستدعي الـ Repository (بسبب قيد once أعلاه)
  $secondResult = $this->action->execute($dto);
  expect($secondResult)->toBe($expectedData);
});


// 📋 --- قسم اختبار الـ AdminOverviewDTO وتغطية شروطه بالكامل ---

test('it creates DTO from request with explicit data', function () {
  $request = Request::create('/analytics', 'GET', [
    'from' => '2026-05-01',
    'to' => '2026-05-27',
    'period' => 'weekly'
  ]);

  $dto = AdminOverviewDTO::fromRequest($request);

  expect($dto->from)->toBe('2026-05-01')
    ->and($dto->to)->toBe('2026-05-27')
    ->and($dto->period)->toBe('weekly');
});

test('it creates DTO with default values when request parameters are missing', function () {
  $request = Request::create('/analytics', 'GET'); // طلب فارغ تماماً

  $dto = AdminOverviewDTO::fromRequest($request);

  // التأكد من التواريخ الافتراضية (الشهر الماضي واليوم)
  expect($dto->from)->toBe(now()->subMonth()->format('Y-m-d'))
    ->and($dto->to)->toBe(now()->format('Y-m-d'))
    ->and($dto->period)->toBe('daily'); // القيمة الافتراضية daily
});

test('it falls back to daily period if an invalid period is provided', function () {
  $request = Request::create('/analytics', 'GET', [
    'period' => 'yearly' // قيمة غير مسموحة (ليست daily, weekly, monthly)
  ]);

  $dto = AdminOverviewDTO::fromRequest($request);

  // يجب أن يعود إلى 'daily' بناءً على الشرط الثلاثي (Ternary Operator) في الـ DTO
  expect($dto->period)->toBe('daily');
});
