<?php

namespace Tests\Unit\Jobs;

use App\Jobs\IncrementSearchViewsJob;
use App\Models\Project; // 🔥 استيراد موديل المشروع
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// ─── 1. اختبار الحقول الإعدادية للـ Job ─────────────────────────────────────
test('it has the correct default configuration for retries and timeout', function () {
  $job = new IncrementSearchViewsJob([1, 2]);

  expect($job->tries)->toBe(2)
    ->and($job->timeout)->toBe(30);
});

// ─── 2. اختبار حالة إرسال مصفوفة فارغة ───────────────────────────────────────
test('it returns early and does not interact with database if entry ids are empty', function () {
  // 🔥 إنشاء مشروع حقيقي لتفادي قيود الـ Foreign Keys
  $project = Project::factory()->create();

  DB::table('search_indices')->insert([
    'entry_id' => 10,
    'view_count' => 5,
    'data_type_id' => 1,
    'project_id' => $project->id, // 🔥 تم الإصلاح هنا
  ]);

  $job = new IncrementSearchViewsJob([]);

  // Act
  $job->handle();

  // Assert
  $currentViewCount = DB::table('search_indices')->where('entry_id', 10)->value('view_count');
  expect($currentViewCount)->toBe(5);
});

// ─── 3. اختبار زيادة العداد الجماعية بنجاح ───────────────────────────────────
test('it increments view count in bulk for the given entry ids', function () {
  $project = Project::factory()->create();

  // 🔥 تم تمرير project_id لكل السجلات المصطنعة
  DB::table('search_indices')->insert([
    ['entry_id' => 101, 'view_count' => 5, 'data_type_id' => 1, 'project_id' => $project->id],
    ['entry_id' => 102, 'view_count' => 0, 'data_type_id' => 1, 'project_id' => $project->id],
    ['entry_id' => 103, 'view_count' => 20, 'data_type_id' => 1, 'project_id' => $project->id],
  ]);

  $job = new IncrementSearchViewsJob([101, 102]);

  // Act
  $job->handle();

  // Assert
  expect(DB::table('search_indices')->where('entry_id', 101)->value('view_count'))->toBe(6);
  expect(DB::table('search_indices')->where('entry_id', 102)->value('view_count'))->toBe(1);

  expect(DB::table('search_indices')->where('entry_id', 103)->value('view_count'))->toBe(20);
});
