<?php

namespace Tests\Unit\Jobs;

use App\Jobs\UpdateSearchSignalsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// الدالة المساعدة بعد إضافة القيم الافتراضية لتفادي قيود الـ NOT NULL
function insertSearchIndex(array $data): void
{
  DB::table('search_indices')->insert(array_merge([
    'entry_id' => 1,
    'data_type_id' => 1,
    'project_id' => 1,
    'click_count' => 0,
    'view_count' => 0,
    'published_at' => now()->toDateTimeString(),
    'ctr_score' => 0.0,
    'freshness_score' => 0.0,
    'popularity_score' => 0.0,
    'created_at' => now(),
    'updated_at' => now(),
  ], $data));
}

// ─── 1. اختبار الحسابات الرياضية الأساسية (CTR, Freshness, Popularity) ───────
test('it correctly calculates all search signals for a freshly published entry', function () {
  // إدخال سجل تم نشره اليوم، يملك 9 نقرات و 19 مشاهدة
  insertSearchIndex([
    'id' => 1,
    'click_count' => 9,
    'view_count' => 19,
    'published_at' => Carbon::now()->toDateTimeString(),
  ]);

  $job = new UpdateSearchSignalsJob();
  $job->handle();

  $result = DB::table('search_indices')->where('id', 1)->first();

  // حساب الـ CTR المتوقع: 9 / (19 + 1) = 9 / 20 = 0.45
  expect($result->ctr_score)->toEqual(0.45);

  // 🔥 تم تعديلها هنا إلى toEqual لتفادي تعارض أنواع البيانات (int vs float) بين SQLite و PHP
  expect($result->freshness_score)->toEqual(1.0);

  // حساب الـ Popularity المتوقع
  expect($result->popularity_score)->toEqual(2.3803);
});

// ─── 2. اختبار معالجة المقالات القديمة وانخفاض معدل الحيوية (Decay) ──────────
test('it applies decay correctly for older entries', function () {
  // سجل تم نشره قبل 7 أيام (أسبوع كامل) بدون نقرات أو مشاهدات
  insertSearchIndex([
    'id' => 2,
    'click_count' => 0,
    'view_count' => 0,
    'published_at' => Carbon::now()->subDays(7)->toDateTimeString(),
  ]);

  $job = new UpdateSearchSignalsJob();
  $job->handle();

  $result = DB::table('search_indices')->where('id', 2)->first();

  // الـ Freshness المتوقع بعد أسبوع: 1 / (7 + 1) = 0.125
  expect($result->freshness_score)->toEqual(0.125);

  // الـ Popularity المتوقع
  expect($result->popularity_score)->toEqual(0.0125);
});

// ─── 3. اختبار القيمة الافتراضية للتواريخ الفارغة (Null published_at) ───────
test('it treats null published_at as 30 days old content', function () {
  // سجل تاريخ النشر فيه Null
  insertSearchIndex([
    'id' => 3,
    'click_count' => 0,
    'view_count' => 0,
    'published_at' => null,
  ]);

  $job = new UpdateSearchSignalsJob();
  $job->handle();

  $result = DB::table('search_indices')->where('id', 3)->first();

  // إذا كان نال، يعتبر قديم بـ 30 يوم حسب شروط الكود: 1 / (30 + 1) = 1 / 31 = 0.0323
  expect($result->freshness_score)->toEqual(0.0323);
});

// ─── 4. اختبار تفرع بيئة الإنتاج والـ Raw SQL الخاص بـ MySQL ─────────────────
test('it executes raw mysql statements when connection driver is mysql', function () {
  Log::spy();

  $connectionMock = \Mockery::mock();
  $connectionMock->shouldReceive('getDriverName')->andReturn('mysql');

  DB::shouldReceive('connection')->andReturn($connectionMock);

  DB::shouldReceive('statement')->times(3);

  $job = new UpdateSearchSignalsJob();
  $job->handle();

  expect(true)->toBeTrue();
});

// ─── 5. اختبار استدعاء كود الفشل عند خروج الـ Job عن مساره (Failed Method) ──
test('it logs error correctly when job fails from queue tier', function () {
  Log::spy();

  $job = new UpdateSearchSignalsJob();
  $job->failed(new \Exception('Queue processing timeout error'));

  Log::shouldHaveReceived('error')->once()->with(
    'UpdateSearchSignalsJob: failed',
    \Mockery::on(fn($args) => $args['error'] === 'Queue processing timeout error')
  );
});
