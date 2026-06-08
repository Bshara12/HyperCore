<?php

namespace Tests\Unit\Jobs;

use App\Jobs\UpdatePopularityScoreJob;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// دالة مساعدة لإنشاء DataType متوافق مع قيود قاعدة البيانات
function createDataType(int $projectId, string $slug = 'test-type-slug'): int
{
  return DB::table('data_types')->insertGetId([
    'name' => 'Test Type',
    'slug' => $slug,
    'project_id' => $projectId,
    'created_at' => now(),
    'updated_at' => now()
  ]);
}

// ─── 1. اختبار الحسابات الرياضية الدقيقة ──────────────────────────────────────
test('it correctly calculates popularity score based on the formula', function () {
  $project = Project::factory()->create();
  $dataTypeId = createDataType($project->id, 'math-type-slug');

  DB::table('data_entries')->insert([
    'id' => 100,
    'ratings_count' => 9,
    'ratings_avg' => 4.0,
    'slug' => 'entry-100',
    'data_type_id' => $dataTypeId,
    'project_id' => $project->id,
  ]);

  DB::table('search_indices')->insert([
    'entry_id' => 100,
    'data_type_id' => $dataTypeId,
    'project_id' => $project->id,
    'click_count' => 9,
    'popularity_score' => 0
  ]);

  $job = new UpdatePopularityScoreJob($project->id);
  $job->handle();

  expect(DB::table('search_indices')->where('entry_id', 100)->value('popularity_score'))
    ->toBe(5.5);
});

// ─── 2. اختبار الحسابات عند انعدام التقييمات (قيم صفرية) ─────────────────────
test('it handles default zero values in ratings', function () {
  $project = Project::factory()->create();
  $dataTypeId = createDataType($project->id, 'zero-type-slug');

  DB::table('data_entries')->insert([
    'id' => 101,
    'ratings_count' => 0,
    'ratings_avg' => 0.0,
    'slug' => 'entry-101',
    'data_type_id' => $dataTypeId,
    'project_id' => $project->id,
  ]);

  DB::table('search_indices')->insert([
    'entry_id' => 101,
    'data_type_id' => $dataTypeId,
    'project_id' => $project->id,
    'click_count' => 0,
    'popularity_score' => 0
  ]);

  $job = new UpdatePopularityScoreJob($project->id);
  $job->handle();

  expect(DB::table('search_indices')->where('entry_id', 101)->value('popularity_score'))
    ->toEqual(0);
});

// ─── 3. اختبار التصفية بمشروع محدد (Project ID Filter) ───────────────────────
test('it updates only for the specific project when projectId is provided', function () {
  $p1 = Project::factory()->create();
  $p2 = Project::factory()->create();

  $dataTypeId1 = createDataType($p1->id, 'slug-project-1');
  $dataTypeId2 = createDataType($p2->id, 'slug-project-2');

  DB::table('data_entries')->insert([
    ['id' => 1, 'ratings_count' => 9, 'ratings_avg' => 4.0, 'slug' => 'slug-1', 'data_type_id' => $dataTypeId1, 'project_id' => $p1->id],
    ['id' => 2, 'ratings_count' => 9, 'ratings_avg' => 4.0, 'slug' => 'slug-2', 'data_type_id' => $dataTypeId2, 'project_id' => $p2->id],
  ]);

  DB::table('search_indices')->insert([
    ['entry_id' => 1, 'data_type_id' => $dataTypeId1, 'project_id' => $p1->id, 'click_count' => 9, 'popularity_score' => 0],
    ['entry_id' => 2, 'data_type_id' => $dataTypeId2, 'project_id' => $p2->id, 'click_count' => 9, 'popularity_score' => 0],
  ]);

  $job = new UpdatePopularityScoreJob($p1->id);
  $job->handle();

  expect(DB::table('search_indices')->where('project_id', $p1->id)->value('popularity_score'))->toBe(5.5)
    ->and(DB::table('search_indices')->where('project_id', $p2->id)->value('popularity_score'))->toEqual(0);
});

// ─── 4. اختبار تحديث جميع المشاريع (عندما يكون Project ID = null) ────────────
test('it updates all projects when projectId is null', function () {
  $p1 = Project::factory()->create();
  $p2 = Project::factory()->create();

  $dataTypeId1 = createDataType($p1->id, 'slug-all-1');
  $dataTypeId2 = createDataType($p2->id, 'slug-all-2');

  DB::table('data_entries')->insert([
    ['id' => 10, 'ratings_count' => 9, 'ratings_avg' => 4.0, 'slug' => 'slug-10', 'data_type_id' => $dataTypeId1, 'project_id' => $p1->id],
    ['id' => 20, 'ratings_count' => 9, 'ratings_avg' => 4.0, 'slug' => 'slug-20', 'data_type_id' => $dataTypeId2, 'project_id' => $p2->id],
  ]);

  DB::table('search_indices')->insert([
    ['entry_id' => 10, 'data_type_id' => $dataTypeId1, 'project_id' => $p1->id, 'click_count' => 9, 'popularity_score' => 0],
    ['entry_id' => 20, 'data_type_id' => $dataTypeId2, 'project_id' => $p2->id, 'click_count' => 9, 'popularity_score' => 0],
  ]);

  // عدم تمرير المعامل يعني null
  $job = new UpdatePopularityScoreJob();
  $job->handle();

  // يجب أن تتحدث درجات كلا المشروعين
  expect(DB::table('search_indices')->where('entry_id', 10)->value('popularity_score'))->toBe(5.5)
    ->and(DB::table('search_indices')->where('entry_id', 20)->value('popularity_score'))->toBe(5.5);
});

// ─── 5. اختبار مسار بيئة الإنتاج MySQL (الـ Else Branch) ─────────────────────
test('it executes raw mysql query when database is not sqlite', function () {
  // نقوم بعمل Mock للاتصال لكي يوهم الكود بأننا في بيئة MySQL
  $connectionMock = \Mockery::mock();
  $connectionMock->shouldReceive('getDriverName')->andReturn('mysql');

  DB::shouldReceive('connection')->andReturn($connectionMock);

  // نتحقق من أن دالة DB::statement يتم استدعاؤها مع تمرير المعاملات (Bindings) الصحيحة
  DB::shouldReceive('statement')
    ->once()
    ->withArgs(function ($sql, $bindings) {
      return str_contains($sql, 'UPDATE search_indices si') && $bindings === [999];
    });

  $job = new UpdatePopularityScoreJob(999);
  $job->handle();
});

// ─── 6. اختبار التقاط الاستثناءات وتسجيلها داخل الـ Handle ───────────────────
test('it catches exceptions in handle, logs them, and rethrows', function () {
  Log::spy();

  // إجبار الكود على الانهيار عند محاولة جلب الاتصال
  DB::shouldReceive('connection')->andThrow(new \Exception('Simulated DB Crash'));

  $job = new UpdatePopularityScoreJob();

  try {
    $job->handle();
  } catch (\Exception $e) {
    expect($e->getMessage())->toBe('Simulated DB Crash');
  }

  // التحقق من أن الكود قام بتسجيل الخطأ في السجل (Log) قبل رميه
  Log::shouldHaveReceived('error')->once()->with(
    'UpdatePopularityScoreJob: failed',
    \Mockery::on(fn($args) => $args['error'] === 'Simulated DB Crash')
  );
});

// ─── 7. اختبار التسجيل عند فشل الـ Job من الطابور (Log Coverage) ─────────────
test('it logs error when job fails from queue', function () {
  Log::spy();

  $job = new UpdatePopularityScoreJob(55);
  $job->failed(new \Exception('Job Failed Exception'));

  Log::shouldHaveReceived('error')->once()->with(
    'UpdatePopularityScoreJob: failed',
    \Mockery::on(function ($args) {
      return $args['project_id'] === 55 && $args['error'] === 'Job Failed Exception';
    })
  );
});
