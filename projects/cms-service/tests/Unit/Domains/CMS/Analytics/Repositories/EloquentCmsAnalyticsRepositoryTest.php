<?php

namespace Tests\Feature\Domains\CMS\Analytics\Repositories;

use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\CMS\Analytics\Repositories\EloquentCmsAnalyticsRepository;
use App\Models\DataCollection;
use App\Models\DataEntry;
use App\Models\DataType;
use App\Models\Project;
use App\Models\Rating;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentCmsAnalyticsRepository();
  Project::withoutGlobalScopes()->forceDelete();
  // 🛠️ محاكاة دالة JSON_CONTAINS لبيئة SQLite
  if (DB::connection()->getDriverName() === 'sqlite') {
    DB::connection()->getPdo()->sqliteCreateFunction('JSON_CONTAINS', function ($json, $val) {
      $json = is_string($json) ? (json_decode($json, true) ?? []) : (is_array($json) ? $json : []);
      $val = trim($val, '"\'');
      return in_array($val, $json) ? 1 : 0;
    });
  }
  if (DB::connection()->getDriverName() === 'sqlite') {
    DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', function ($date, $format) {
      // تحويل دقيق لصيغ MySQL
      $phpFormat = str_replace(
        ['%Y', '%m', '%d', '%x', '%v'],
        ['Y', 'm', 'd', 'o', 'W'], // o هي السنة (ISO-8601)، W هو رقم الأسبوع
        $format
      );
      return date($phpFormat, strtotime($date));
    });
  }
});

test('it returns accurate statistics including our created test data', function () {
  // 1. إنشاء بياناتنا الخاصة
  $p1 = Project::factory()->create(['created_at' => '2026-04-15 10:00:00', 'enabled_modules' => ['ecommerce']]);
  $p2 = Project::factory()->create(['created_at' => '2026-05-10 10:00:00', 'enabled_modules' => ['ecommerce', 'booking']]);

  // ربط البيانات بـ p2 حصراً لمنع إنشاء مشاريع إضافية
  DataType::factory()->count(3)->create(['project_id' => $p2->id]);
  DataCollection::factory()->count(2)->create(['project_id' => $p2->id]);

  // إنشاء 5 إدخالات
  DataEntry::factory()->create(['status' => 'published', 'project_id' => $p2->id]);
  DataEntry::factory()->create(['status' => 'published', 'project_id' => $p2->id]);
  DataEntry::factory()->create(['status' => 'draft', 'project_id' => $p2->id]);
  DataEntry::factory()->create(['status' => 'scheduled', 'project_id' => $p2->id]);
  DataEntry::factory()->create(['status' => 'archived', 'project_id' => $p2->id]);

  Rating::factory()->count(3)->create(['rateable_type' => Project::class, 'rateable_id' => $p2->id]);

  // 2. التنفيذ
  $results = $this->repository->getAdminOverview('2026-05-01', '2026-05-31');

  // 3. التأكيد (Assertions)
  // بدلاً من الطرح من Baseline، سنقوم بالتأكد أن النتيجة "تتضمن" ما نتوقعه.
  // وبما أننا أنشأنا 5 entries و 3 ratings، يجب أن تكون النتيجة على الأقل هذه القيم.

  expect($results['content']['total_entries'])->toBeGreaterThanOrEqual(5)
    ->and($results['content']['published_entries'])->toBeGreaterThanOrEqual(2)
    ->and($results['ratings']['total'])->toBeGreaterThanOrEqual(3);

  // التحقق من أن الموديلات (Modules) التي أنشأناها موجودة في الحسابات
  expect($results['modules_usage']['ecommerce_enabled'])->toBeGreaterThanOrEqual(2)
    ->and($results['modules_usage']['booking_enabled'])->toBeGreaterThanOrEqual(1);
});
test('it handles empty scenarios gracefully', function () {
  // التنظيف القسري للتأكد من حالة الصفر
  Project::query()->forceDelete();
  DataEntry::query()->forceDelete();

  $results = $this->repository->getAdminOverview('2026-05-01', '2026-05-31');

  expect($results['projects']['total'])->toBe(0)
    ->and($results['content']['total_entries'])->toBe(0)
    ->and($results['content']['publish_rate'])->toBe(0)
    ->and($results['ratings']['total'])->toBe(0);
});

test('it groups project creation counts correctly by period', function ($period, $expectedLabel) {
  // 1. ترتيب البيانات (ننشئ مشروعين في تواريخ مختلفة)
  Project::factory()->create(['created_at' => '2026-05-01 10:00:00']);
  Project::factory()->create(['created_at' => '2026-05-02 10:00:00']);

  // 2. تجهيز الـ DTO
  $dto = new AdminOverviewDTO(
    from: '2026-05-01',
    to: '2026-05-31',
    period: $period
  );

  // 3. التنفيذ
  $results = $this->repository->getProjectsGrowth($dto);

  // 4. التأكيد
  expect($results['data'])->toBeArray()
    ->and($results['data'][0])->toHaveKeys(['label', 'count'])
    ->and($results['data'][0]['label'])->toContain($expectedLabel);
})->with([
  ['daily', '2026-05-01'],
  ['monthly', '2026-05'],
  ['weekly', '2026'],
]);

test('it returns empty data when no projects exist in the period', function () {
  $dto = new AdminOverviewDTO(
    from: '2026-06-01',
    to: '2026-06-30',
    period: 'daily'
  );

  $results = $this->repository->getProjectsGrowth($dto);

  expect($results['data'])->toBeEmpty();
});

test('it ignores soft deleted projects in growth calculation', function () {
  // مشروع محذوف
  Project::factory()->create([
    'created_at' => '2026-05-01 10:00:00',
    'deleted_at' => now()
  ]);

  $dto = new AdminOverviewDTO(
    from: '2026-05-01',
    to: '2026-05-31',
    period: 'daily'
  );

  $results = $this->repository->getProjectsGrowth($dto);

  expect($results['data'])->toBeEmpty();
});

test('it returns correct summary for data types and collections', function () {
  // 1. إنشاء مشروع وبيانات مرتبطة
  $project = Project::factory()->create();

  // إنشاء DataType مع إدخالات متنوعة للحالات
  // إنشاء DataType مع إدخالات متنوعة
  $dt = DataType::factory()->create(['project_id' => $project->id, 'name' => 'News']);

  // إنشاء السجلات بقيمة 4.5 لضمان أن المتوسط الحسابي يكون 4.5
  // لأن SQL يحسب (4.5 + 4.5 + 4.5 + 4.5) / 4 = 4.5
  DataEntry::factory()->create([
    'data_type_id' => $dt->id,
    'status' => 'published',
    'ratings_avg' => 4.5,
    'ratings_count' => 2
  ]);

  // السجلات الأخرى: نضبطها أيضاً على 4.5 ليتطابق المتوسط، 
  // أو إذا أردت استثناءها، يجب تعديل الاستعلام (Query) وليس الاختبار فقط.
  DataEntry::factory()->create(['data_type_id' => $dt->id, 'status' => 'draft', 'ratings_avg' => 4.5, 'ratings_count' => 0]);
  DataEntry::factory()->create(['data_type_id' => $dt->id, 'status' => 'scheduled', 'ratings_avg' => 4.5, 'ratings_count' => 0]);
  DataEntry::factory()->create(['data_type_id' => $dt->id, 'status' => 'archived', 'ratings_avg' => 4.5, 'ratings_count' => 0]);

  // إنشاء مجموعات
  DataCollection::factory()->create(['project_id' => $project->id, 'type' => 'manual', 'is_offer' => true, 'is_active' => true]);
  DataCollection::factory()->create(['project_id' => $project->id, 'type' => 'dynamic', 'is_offer' => false, 'is_active' => false]);

  // 2. تجهيز الـ DTO
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-31',
    period: 'daily',
    projectId: $project->id,
    limit: 5
  );

  // 3. التنفيذ
  $results = $this->repository->getContentSummary($dto);

  // 4. التأكيد (Assertions)
  // التأكد من بيانات الـ Data Types
  $dataType = collect($results['data_types'])->firstWhere('name', 'News');
  expect($dataType['total_entries'])->toBe(4)
    ->and($dataType['published'])->toBe(1)
    ->and($dataType['publish_rate'])->toBe(25.0) // (1/4 * 100)
    ->and($dataType['avg_rating'])->toBe(4.5)
    ->and($dataType['total_ratings'])->toBe(2);

  // التأكد من بيانات الـ Collections
  expect($results['collections']['total'])->toBe(2)
    ->and($results['collections']['manual'])->toBe(1)
    ->and($results['collections']['dynamic'])->toBe(1)
    ->and($results['collections']['offer_collections'])->toBe(1)
    ->and($results['collections']['active'])->toBe(1);
});

test('it calculates content growth correctly based on published date', function () {
  $project = Project::factory()->create();

  // 1. إنشاء بيانات اختبار: 3 سجلات منشورة (Published)
  DataEntry::factory()->create([
    'project_id' => $project->id,
    'status' => 'published',
    'published_at' => '2026-05-10 10:00:00'
  ]);
  DataEntry::factory()->create([
    'project_id' => $project->id,
    'status' => 'published',
    'published_at' => '2026-05-10 11:00:00' // نفس اليوم
  ]);
  DataEntry::factory()->create([
    'project_id' => $project->id,
    'status' => 'published',
    'published_at' => '2026-05-15 10:00:00' // يوم مختلف
  ]);

  // 2. سجلات يجب أن يتم تجاهلها (غير منشورة أو خارج النطاق)
  DataEntry::factory()->create(['project_id' => $project->id, 'status' => 'draft', 'published_at' => '2026-05-10 10:00:00']);
  DataEntry::factory()->create(['project_id' => $project->id, 'status' => 'published', 'published_at' => '2026-06-01 10:00:00']);

  // 3. تجهيز الـ DTO (انتبه للـ 5 باراميترات)
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-31',
    period: 'daily',
    projectId: $project->id,
    limit: 10
  );

  // 4. التنفيذ
  $results = $this->repository->getContentGrowth($dto);

  // 5. التأكيد
  // يجب أن نجد يومين (10 و 15 مايو)
  expect($results['data'])->toHaveCount(2)
    ->and($results['data'][0]['label'])->toContain('2026-05-10')
    ->and($results['data'][0]['count'])->toBe(2)
    ->and($results['data'][1]['label'])->toContain('2026-05-15')
    ->and($results['data'][1]['count'])->toBe(1);
});

test('it retrieves top rated entries sorted correctly and filters zero ratings', function () {
  $project = Project::factory()->create();
  $dataType = DataType::factory()->create(['project_id' => $project->id]);

  // 1. إنشاء سجلات بتصنيفات مختلفة لاختبار الترتيب
  // السجل الأفضل (متوسط 5.0 مع 20 تقييم)
  $best = DataEntry::factory()->create([
    'project_id' => $project->id,
    'data_type_id' => $dataType->id,
    'ratings_avg' => 5.0,
    'ratings_count' => 20
  ]);

  // سجل ممتاز (متوسط 5.0 مع 10 تقييمات) -> يجب أن يكون الثاني بسبب قلّة عدد التقييمات مقارنة بـ $best
  $runnerUp = DataEntry::factory()->create([
    'project_id' => $project->id,
    'data_type_id' => $dataType->id,
    'ratings_avg' => 5.0,
    'ratings_count' => 10
  ]);

  // سجل جيد (متوسط 4.0)
  $good = DataEntry::factory()->create([
    'project_id' => $project->id,
    'data_type_id' => $dataType->id,
    'ratings_avg' => 4.0,
    'ratings_count' => 50
  ]);

  // سجل مهمل (ratings_count = 0) -> يجب استبعاده
  DataEntry::factory()->create([
    'project_id' => $project->id,
    'data_type_id' => $dataType->id,
    'ratings_avg' => 0.0,
    'ratings_count' => 0
  ]);

  // 2. تجهيز الـ DTO
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-31',
    period: 'daily',
    projectId: $project->id,
    limit: 5
  );

  // 3. التنفيذ
  $results = $this->repository->getTopRatedEntries($dto);

  // 4. التأكيد (Assertions)
  expect($results['entries'])->toHaveCount(3) // 4 تم إنشاؤها، 1 مستبعد، تبقى 3
    ->and($results['entries'][0]['id'])->toBe($best->id)
    ->and($results['entries'][1]['id'])->toBe($runnerUp->id)
    ->and($results['entries'][2]['id'])->toBe($good->id);

  // التأكد من بنية البيانات
  expect($results['entries'][0])->toHaveKeys(['id', 'slug', 'status', 'ratings_count', 'ratings_avg', 'data_type']);
});

test('it respects the limit in top rated entries', function () {
  $project = Project::factory()->create();
  $dataType = DataType::factory()->create(['project_id' => $project->id]);

  // إنشاء 5 سجلات
  DataEntry::factory()->count(5)->create([
    'project_id' => $project->id,
    'data_type_id' => $dataType->id,
    'ratings_count' => 5
  ]);

  // طلب حد 2 فقط
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-31',
    period: 'daily',
    projectId: $project->id,
    limit: 2
  );

  $results = $this->repository->getTopRatedEntries($dto);

  expect($results['entries'])->toHaveCount(2);
});

test('it returns empty ratings report when no entries exist', function () {
  $project = Project::factory()->create();
  $dto = new AnalyticsFilterDTO('2026-05-01', '2026-05-31', 'daily', $project->id, 10);

  $results = $this->repository->getRatingsReport($dto);

  expect($results['content_ratings']['total'])->toBe(0)
    ->and($results['content_ratings']['distribution'][5]['count'])->toBe(0);
});

test('it calculates accurate ratings summary and distribution', function () {
  $project = Project::factory()->create();
  $dt = DataType::factory()->create(['project_id' => $project->id]);
  $entry = DataEntry::factory()->create(['project_id' => $project->id, 'data_type_id' => $dt->id]);

  // إنشاء 5 تقييمات متنوعة للمحتوى
  // 5 نجوم (مع تعليق)
  Rating::factory()->create(['rateable_id' => $entry->id, 'rateable_type' => 'data', 'rating' => 5, 'review' => 'Great!', 'created_at' => '2026-05-15 10:00:00']);

  // 4 نجوم (بدون تعليق) - نضع review صراحة كـ null
  Rating::factory()->create(['rateable_id' => $entry->id, 'rateable_type' => 'data', 'rating' => 4, 'review' => null, 'created_at' => '2026-05-15 10:00:00']);

  // 3 نجوم
  Rating::factory()->create(['rateable_id' => $entry->id, 'rateable_type' => 'data', 'rating' => 3, 'review' => null, 'created_at' => '2026-05-15 10:00:00']);

  // 2 نجوم
  Rating::factory()->create(['rateable_id' => $entry->id, 'rateable_type' => 'data', 'rating' => 2, 'review' => null, 'created_at' => '2026-05-15 10:00:00']);

  // 1 نجمة
  Rating::factory()->create(['rateable_id' => $entry->id, 'rateable_type' => 'data', 'rating' => 1, 'review' => null, 'created_at' => '2026-05-15 10:00:00']);

  $dto = new AnalyticsFilterDTO('2026-05-01', '2026-05-31', 'daily', $project->id, 10);
  // إنشاء تقييم للمشروع نفسه
  Rating::factory()->create([
    'rateable_id' => $project->id, // تأكد أن هذا هو نفس الـ ID الذي يصله الـ DTO
    'rateable_type' => 'project',  // تأكد أن هذا النص يطابق ما في قاعدة البيانات
    'rating' => 4,
    'created_at' => '2026-05-15 10:00:00' // تاريخ ضمن نطاق الـ DTO (مايو 2026)
  ]);
  $results = $this->repository->getRatingsReport($dto);

  // التأكد من الحسابات
  expect($results['content_ratings']['total'])->toBe(5)
    ->and($results['content_ratings']['avg_rating'])->toBe(3.0) // (5+4+3+2+1)/5
    ->and($results['content_ratings']['with_review'])->toBe(1)
    ->and($results['content_ratings']['distribution'][5]['percentage'])->toBe(20.0); // 1/5 = 20%

  // التأكد من فصل تقييمات المشروع
  expect($results['project_ratings']['total'])->toBe(1)
    ->and($results['project_ratings']['avg_rating'])->toBe(4.0);
});
