<?php

namespace Tests\Unit\Jobs;

use App\Jobs\IncrementViewCountJob;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

// ─── 1. اختبار المسار الفارغ (العودة المبكرة) ────────────────────────────────
test('it returns early if entry ids are empty', function () {
  // نستخدم Spy بدلاً من Mock للـ Log لنراقب فقط ما حدث
  Log::spy();

  $job = new IncrementViewCountJob([]);
  $job->handle();

  // تأكيد أن قاعدة البيانات لم تلمس
  expect(DB::table('search_indices')->count())->toBe(0);
});

// ─── 2. اختبار المسار الكامل (تغطية منطق array_unique و array_map) ─────────
test('it increments unique integer ids and respects language filter', function () {
  Log::spy();
  $project = Project::factory()->create();

  // نجهز البيانات: إدخال 3 سجلات
  DB::table('search_indices')->insert([
    ['entry_id' => 10, 'view_count' => 0, 'language' => 'en', 'project_id' => $project->id, 'data_type_id' => 1],
    ['entry_id' => 20, 'view_count' => 0, 'language' => 'en', 'project_id' => $project->id, 'data_type_id' => 1],
    ['entry_id' => 30, 'view_count' => 0, 'language' => 'ar', 'project_id' => $project->id, 'data_type_id' => 1],
  ]);

  // نمرر مصفوفة تحتوي تكرار (10) وسلسلة نصية ('20') للتأكد من عمل array_unique و intval
  $job = new IncrementViewCountJob([10, '10', 20], 'en');
  $job->handle();

  // التأكد من أن 10 و 20 فقط زادوا (رغم التكرار في المدخلات)
  expect(DB::table('search_indices')->where('entry_id', 10)->value('view_count'))->toBe(1)
    ->and(DB::table('search_indices')->where('entry_id', 20)->value('view_count'))->toBe(1)
    ->and(DB::table('search_indices')->where('entry_id', 30)->value('view_count'))->toBe(0); // اللغة مختلفة

  // التأكد من أن الـ Log تم استدعاؤه (تغطية سطر Log::debug)
  Log::shouldHaveReceived('debug')->once();
});

// ─── 3. اختبار دالة الفشل (تغطية سطر Log::warning) ──────────────────────────
test('it logs warning when job fails', function () {
  // نستخدم spy لمراقبة النداء
  Log::spy();

  $exception = new \Exception('Database Connection Failed');
  $job = new IncrementViewCountJob([1, 2, 3]);

  $job->failed($exception);

  // التأكد من أن الـ Log تم استدعاؤه بـ warning
  Log::shouldHaveReceived('warning')->once()->with(
    'IncrementViewCountJob: failed',
    \Mockery::on(function ($args) {
      return $args['error'] === 'Database Connection Failed';
    })
  );
});
