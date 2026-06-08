<?php

namespace Tests\Feature\Domains\CMS\Analytics\Actions;

use App\Domains\CMS\Analytics\Actions\GetRatingsReportAction;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;

beforeEach(function () {
  // 1. محاكاة الـ currentProject لتهيئة الـ DTO بسلام
  $this->mockedCurrentProject = (object) ['public_id' => 'proj_ratings77'];
  app()->instance('currentProject', $this->mockedCurrentProject);

  // 2. إنشاء مودل حقيقي لـ Project وتعيين معرفه لتخطي قيود الـ Type Hinting
  $mockProject = new Project();
  $mockProject->id = 77; // سنعتمد المعرف 77 في اختبارات هذا الملف

  $this->projectRepoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $this->projectRepoMock->shouldReceive('findByKey')
    ->with('proj_ratings77')
    ->andReturn($mockProject);
  app()->instance(ProjectRepositoryInterface::class, $this->projectRepoMock);

  // 3. محاكاة مستودع التحليلات وتجهيز الـ Action المستهدف
  $this->analyticsRepoMock = Mockery::mock(AnalyticsRepositoryInterface::class);
  $this->action = new GetRatingsReportAction($this->analyticsRepoMock);
});

afterEach(function () {
  Mockery::close();
});

// 🧠 --- قسم اختبار الـ GetRatingsReportAction ونظام الكاش الديناميكي ---

test('it returns ratings report from repository and caches it, then hits cache next time', function () {
  // تجهيز كائن الـ DTO بقيم مخصصة للاختبار
  $dto = new AnalyticsFilterDTO(
    from: '2026-04-01',
    to: '2026-04-30',
    period: 'weekly',
    projectId: 77,
    limit: 10
  );

  $expectedData = [
    'average_rating' => 4.7,
    'total_ratings' => 340,
    'distribution' => [5 => 200, 4 => 100, 3 => 40]
  ];

  // بناء مفتاح الكاش المتوقع والمطابق تماماً لصياغة الـ Action الحالية ⭐
  $cacheKey = "analytics:project:77:ratings:weekly:2026-04-01:2026-04-30";

  // تصفية مفتاح الكاش احتياطاً قبل البدء
  Cache::forget($cacheKey);

  // إلزام الـ Mock بالاستجابة مرة واحدة فقط (once) لسيناريو الـ Cache Miss
  $this->analyticsRepoMock->shouldReceive('getRatingsReport')
    ->once()
    ->with($dto)
    ->andReturn($expectedData);

  // 1. الاستدعاء الأول: (Cache Miss) -> يمر عبر المستودع ويخزن في الكاش
  $firstResult = $this->action->execute($dto);
  expect($firstResult)->toBe($expectedData);
  expect(Cache::has($cacheKey))->toBeTrue();

  // 2. الاستدعاء الثاني: (Cache Hit) -> يعود من الكاش مباشرة وبسرعة فائقة
  $secondResult = $this->action->execute($dto);
  expect($secondResult)->toBe($expectedData);
});


// 📋 --- قسم اختبار الـ AnalyticsFilterDTO وتغطية حالات الـ Request المتنوعة ---

test('it creates DTO from request with full explicit parameters', function () {
  $request = Request::create('/analytics/ratings', 'GET', [
    'from' => '2026-05-01',
    'to' => '2026-05-27',
    'period' => 'monthly',
    'limit' => 15
  ]);

  $dto = AnalyticsFilterDTO::fromRequest($request);

  expect($dto->from)->toBe('2026-05-01')
    ->and($dto->to)->toBe('2026-05-27')
    ->and($dto->period)->toBe('monthly')
    ->and($dto->projectId)->toBe(77) // مؤمن عبر الـ Container Mock
    ->and($dto->limit)->toBe(15);
});

test('it creates DTO with system default values when request parameters are missing', function () {
  $request = Request::create('/analytics/ratings', 'GET'); // طلب فارغ تماماً

  $dto = AnalyticsFilterDTO::fromRequest($request);

  // تأكيد قيم النظام الافتراضية
  expect($dto->from)->toBe(now()->subMonth()->format('Y-m-d'))
    ->and($dto->to)->toBe(now()->format('Y-m-d'))
    ->and($dto->period)->toBe('daily')
    ->and($dto->projectId)->toBe(77)
    ->and($dto->limit)->toBe(10);
});

test('it forces daily period fallback if an invalid period is provided', function () {
  $request = Request::create('/analytics/ratings', 'GET', [
    'period' => 'yearly' // قيمة غير مدعومة في الـ array المسموح به
  ]);

  $dto = AnalyticsFilterDTO::fromRequest($request);

  // يجب أن يعود تلقائياً إلى 'daily' لتغطية السطر البرمجي الأخير بالكامل
  expect($dto->period)->toBe('daily');
});
