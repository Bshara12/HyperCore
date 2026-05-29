<?php

use App\Models\DataEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('it publishes scheduled entries that are due', function () {
  // 1. إنشاء سجل مجدول حان وقته
  $entryDue = DataEntry::factory()->create([
    'status' => 'scheduled',
    'scheduled_at' => Carbon::now()->subMinutes(5),
  ]);

  // 2. إنشاء سجل مجدول لم يحن وقته بعد
  $entryNotDue = DataEntry::factory()->create([
    'status' => 'scheduled',
    'scheduled_at' => Carbon::now()->addMinutes(5),
  ]);

  // 3. تنفيذ الأمر
  $this->artisan('app:publish-scheduled-entries')->assertExitCode(0);

  // 4. التأكد من تحديث الحالة للسجل الأول وعدم تحديث الثاني
  expect($entryDue->fresh()->status)->toBe('published')
    ->and($entryDue->fresh()->published_at)->not->toBeNull()
    ->and($entryNotDue->fresh()->status)->toBe('scheduled');
});

test('it handles empty scheduled entries gracefully', function () {
  // تنفيذ الأمر في حال عدم وجود أي سجلات
  $this->artisan('app:publish-scheduled-entries')->assertExitCode(0);

  expect(DataEntry::where('status', 'published')->count())->toBe(0);
});
