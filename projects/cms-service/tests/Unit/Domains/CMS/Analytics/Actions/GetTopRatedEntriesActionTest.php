<?php

namespace Tests\Feature\Domains\CMS\Analytics\Actions;

use App\Domains\CMS\Analytics\Actions\GetTopRatedEntriesAction;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;

beforeEach(function () {
  // 1. محاكاة الـ currentProject لخدمة الـ DTO بسلام
  $this->mockedCurrentProject = (object) ['public_id' => 'proj_top_entries'];
  app()->instance('currentProject', $this->mockedCurrentProject);

  // 2. إنشاء مودل حقيقي لـ Project لتخطي قيود الـ Type Hinting الخاص بالـ Repository
  $mockProject = new Project();
  $mockProject->id = 66; // سنعتمد المعرف 66 في اختبارات هذا الملف

  $this->projectRepoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $this->projectRepoMock->shouldReceive('findByKey')
    ->with('proj_top_entries')
    ->andReturn($mockProject);
  app()->instance(ProjectRepositoryInterface::class, $this->projectRepoMock);

  // 3. محاكاة مستودع التحليلات وتجهيز الـ Action المستهدف ⭐
  $this->analyticsRepoMock = Mockery::mock(AnalyticsRepositoryInterface::class);
  $this->action = new GetTopRatedEntriesAction($this->analyticsRepoMock);
});

afterEach(function () {
  Mockery::close();
});

// 🧠 --- قسم اختبار الـ GetTopRatedEntriesAction وسلوك الكاش المبني على الـ Limit ---

test('it returns top rated entries from repository and caches it based on project id and limit', function () {
  // تجهيز كائن الـ DTO بـ limit مخصص (مثلاً: 5 عناصر)
  $dto = new AnalyticsFilterDTO(
    from: '2026-01-01',
    to: '2026-05-27',
    period: 'daily',
    projectId: 66,
    limit: 5
  );

  $expectedData = [
    ['entry_id' => 101, 'title' => 'Product A', 'rating' => 4.9],
    ['entry_id' => 102, 'title' => 'Product B', 'rating' => 4.85],
  ];

  // بناء مفتاح الكاش المتوقع والمطابق تماماً لصياغة الـ Action الحالية (تذكر أنه يعتمد على الـ limit) ⭐
  $cacheKey = "analytics:project:66:top_rated:5";

  // تصفية مفتاح الكاش احتياطاً قبل البدء
  Cache::forget($cacheKey);

  // إلزام الـ Mock بالاستجابة مرة واحدة فقط (once) لسيناريو الـ Cache Miss
  $this->analyticsRepoMock->shouldReceive('getTopRatedEntries')
    ->once()
    ->with($dto)
    ->andReturn($expectedData);

  // 1. الاستدعاء الأول: (Cache Miss) -> يمر عبر المستودع ويخزن في الكاش
  $firstResult = $this->action->execute($dto);
  expect($firstResult)->toBe($expectedData);
  expect(Cache::has($cacheKey))->toBeTrue();

  // 2. الاستدعاء الثاني: (Cache Hit) -> يعود من الكاش مباشرة بدون استدعاء المستودع مجدداً
  $secondResult = $this->action->execute($dto);
  expect($secondResult)->toBe($expectedData);
});


// 📋 --- قسم اختبار الـ AnalyticsFilterDTO وتغطية حالات الـ Request المتنوعة ---

test('it creates DTO from request with full explicit parameters', function () {
  $request = Request::create('/analytics/top-rated', 'GET', [
    'from' => '2026-05-01',
    'to' => '2026-05-27',
    'period' => 'weekly',
    'limit' => 20 // قيمة مخصصة للـ limit
  ]);

  $dto = AnalyticsFilterDTO::fromRequest($request);

  expect($dto->from)->toBe('2026-05-01')
    ->and($dto->to)->toBe('2026-05-27')
    ->and($dto->period)->toBe('weekly')
    ->and($dto->projectId)->toBe(66)
    ->and($dto->limit)->toBe(20);
});

test('it creates DTO with system default values when request parameters are missing', function () {
  $request = Request::create('/analytics/top-rated', 'GET'); // طلب فارغ تماماً

  $dto = AnalyticsFilterDTO::fromRequest($request);

  // تأكيد قيم النظام الافتراضية (الـ limit الافتراضي هو 10)
  expect($dto->from)->toBe(now()->subMonth()->format('Y-m-d'))
    ->and($dto->to)->toBe(now()->format('Y-m-d'))
    ->and($dto->period)->toBe('daily')
    ->and($dto->projectId)->toBe(66)
    ->and($dto->limit)->toBe(10);
});

test('it forces daily period fallback if an invalid period is provided', function () {
  $request = Request::create('/analytics/top-rated', 'GET', [
    'period' => 'yearly' // قيمة غير مدعومة
  ]);

  $dto = AnalyticsFilterDTO::fromRequest($request);

  expect($dto->period)->toBe('daily');
});
