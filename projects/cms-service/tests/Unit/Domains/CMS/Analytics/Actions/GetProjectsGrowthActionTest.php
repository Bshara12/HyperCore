<?php

namespace Tests\Feature\Domains\CMS\Analytics\Actions;

use App\Domains\CMS\Analytics\Actions\GetProjectsGrowthAction;
use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;

beforeEach(function () {
  // 1. محاكاة مستودع التحليلات
  $this->analyticsRepoMock = Mockery::mock(AnalyticsRepositoryInterface::class);

  // 2. تجهيز الـ Action وحقن الـ Mock بداخلها
  $this->action = new GetProjectsGrowthAction($this->analyticsRepoMock);
});

afterEach(function () {
  Mockery::close();
});

// 🧠 --- قسم اختبار الـ GetProjectsGrowthAction وسلوك الكاش ---

test('it returns projects growth data from repository and caches it, then hits cache', function () {
  // تجهيز الـ DTO بقيم مخصصة للاختبار
  $dto = new AdminOverviewDTO(from: '2026-01-01', to: '2026-01-31', period: 'monthly');

  $expectedData = [
    'total_projects_created' => 150,
    'growth_percentage' => 12.5,
    'chart_data' => [10, 20, 30]
  ];

  // بناء مفتاح الكاش المتوقع بدقة بناءً على تركيبة الـ Action ⭐
  $cacheKey = "analytics:admin:projects_growth:monthly:2026-01-01:2026-01-31";

  // تصفية الكاش لضمان بدء الفحص بـ Cache Miss
  Cache::forget($cacheKey);

  // نحدد أن المستودع سيستدعى مرة واحدة فقط لتوليد البيانات وتخزينها
  $this->analyticsRepoMock->shouldReceive('getProjectsGrowth')
    ->once()
    ->with($dto)
    ->andReturn($expectedData);

  // 1. الاستدعاء الأول: (Cache Miss) -> يمر عبر الـ Repository ويخزن في الكاش
  $firstResult = $this->action->execute($dto);
  expect($firstResult)->toBe($expectedData);
  expect(Cache::has($cacheKey))->toBeTrue();

  // 2. الاستدعاء الثاني: (Cache Hit) -> يجلب البيانات مباشرة من الكاش دون استدعاء المستودع مجدداً
  $secondResult = $this->action->execute($dto);
  expect($secondResult)->toBe($expectedData);
});


// 📋 --- قسم اختبار الـ AdminOverviewDTO وتغطية شروطه بالكامل ---

test('it creates DTO from request with full explicit parameters', function () {
  $request = Request::create('/analytics/admin/projects-growth', 'GET', [
    'from' => '2026-05-01',
    'to' => '2026-05-27',
    'period' => 'weekly'
  ]);

  $dto = AdminOverviewDTO::fromRequest($request);

  expect($dto->from)->toBe('2026-05-01')
    ->and($dto->to)->toBe('2026-05-27')
    ->and($dto->period)->toBe('weekly');
});

test('it creates DTO with system default values when request parameters are missing', function () {
  $request = Request::create('/analytics/admin/projects-growth', 'GET'); // طلب فارغ

  $dto = AdminOverviewDTO::fromRequest($request);

  // التأكيد على التواريخ الافتراضية (الشهر الماضي واليوم) والـ period الافتراضي (daily)
  expect($dto->from)->toBe(now()->subMonth()->format('Y-m-d'))
    ->and($dto->to)->toBe(now()->format('Y-m-d'))
    ->and($dto->period)->toBe('daily');
});

test('it falls back to daily period if an invalid period is provided', function () {
  $request = Request::create('/analytics/admin/projects-growth', 'GET', [
    'period' => 'yearly' // قيمة غير مدعومة في مصفوفة الـ in_array
  ]);

  $dto = AdminOverviewDTO::fromRequest($request);

  // يجب أن يعود تلقائياً إلى 'daily' بناءً على الشرط الثلاثي في الـ DTO
  expect($dto->period)->toBe('daily');
});
