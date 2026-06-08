<?php

namespace Tests\Feature\Domains\CMS\Analytics\Actions;

use App\Domains\CMS\Analytics\Actions\GetContentSummaryAction;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Analytics\Repositories\AnalyticsRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;

beforeEach(function () {
  // 1. محاكاة الـ currentProject في حاوية لارافل لخدمة الـ DTO
  $this->mockedCurrentProject = (object) ['public_id' => 'proj_summary123'];
  app()->instance('currentProject', $this->mockedCurrentProject);

  // 2. إنشاء المودل الحقيقي لتخطي قيود الـ Return Type الخاص بالـ Repository
  $mockProject = new Project();
  $mockProject->id = 88; // سنستخدم معرف مشروع مختلف (88) للتأكيد

  $this->projectRepoMock = Mockery::mock(ProjectRepositoryInterface::class);
  $this->projectRepoMock->shouldReceive('findByKey')
    ->with('proj_summary123')
    ->andReturn($mockProject);
  app()->instance(ProjectRepositoryInterface::class, $this->projectRepoMock);

  // 3. محاكاة مستودع التحليلات وتجهيز الـ Action الجديد ⭐
  $this->analyticsRepoMock = Mockery::mock(AnalyticsRepositoryInterface::class);
  $this->action = new GetContentSummaryAction($this->analyticsRepoMock);
});

afterEach(function () {
  Mockery::close();
});

// 🧠 --- قسم اختبار الـ GetContentSummaryAction وسلوك الكاش ---

test('it returns content summary from repository and caches it precisely by project id', function () {
  // بناء الـ DTO (يمكن بناؤه عبر Request أو يدوياً)
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-27',
    period: 'daily',
    projectId: 88,
    limit: 10
  );

  $expectedData = [
    'total_articles' => 45,
    'published_count' => 30,
    'draft_count' => 15
  ];

  // مفتاح الكاش المتوقع لهذا الـ Action بالتحديد ⭐
  $cacheKey = "analytics:project:88:content_summary";

  // تصفية مفتاح الكاش لضمان بدء الفحص بـ Cache Miss
  Cache::forget($cacheKey);

  // التأكيد على أن المستودع سيستدعى مرة واحدة فقط لتوليد البيانات
  $this->analyticsRepoMock->shouldReceive('getContentSummary')
    ->once()
    ->with($dto)
    ->andReturn($expectedData);

  // 1. الاستدعاء الأول: (Cache Miss) -> يمر بالـ Repository ويخزن النتيجة
  $firstResult = $this->action->execute($dto);
  expect($firstResult)->toBe($expectedData);
  expect(Cache::has($cacheKey))->toBeTrue();

  // 2. الاستدعاء الثاني: (Cache Hit) -> يجلب البيانات مباشرة من الكاش دون الرجوع للمستودع
  $secondResult = $this->action->execute($dto);
  expect($secondResult)->toBe($expectedData);
});

// 📋 --- اختبار التكامل بين الـ DTO المستخرج من الـ Request والـ Action ---

test('it executes successfully when integrated with DTO created from HTTP request', function () {
  // محاكاة طلب HTTP قادم من المتصفح
  $request = Request::create('/analytics/summary', 'GET', [
    'from' => '2026-01-01',
    'limit' => 5
  ]);

  // توليد الـ DTO عبر الـ request (سينفذ كود الحاوية والمودل المجهزة في beforeEach بسلام)
  $dto = AnalyticsFilterDTO::fromRequest($request);

  $expectedData = ['total_items' => 100];

  // إعلام الـ Mock بانتظار استدعاء الدالة
  $this->analyticsRepoMock->shouldReceive('getContentSummary')
    ->once()
    ->with($dto)
    ->andReturn($expectedData);

  // تنفيذ الـ Action
  $result = $this->action->execute($dto);

  expect($result)->toBe($expectedData)
    ->and($dto->projectId)->toBe(88) // تأكيد أن الدمج جلب معرف المشروع الصحيح
    ->and($dto->limit)->toBe(5);
});
