<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

beforeEach(function () {
  // 1. بناء جداول وهمية في الذاكرة لتشغيل الاختبار عليها بشكل معزول
  Schema::create('logs', function (Blueprint $table) {
    $table->id();
    $table->timestamp('occurred_at');
  });

  Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->timestamp('occurred_at');
  });
});

afterEach(function () {
  // 2. تنظيف قاعدة البيانات وحذف الجداول المؤقتة بعد نهاية كل اختبار
  Schema::dropIfExists('logs');
  Schema::dropIfExists('audit_logs');
});

it('cleans up logs older than 90 days and leaves recent ones', function () {
  // 3. تجهيز بيانات اختبار لجدول الـ logs (سجل قديم وسجل جديد)
  DB::table('logs')->insert([
    ['occurred_at' => now()->subDays(91)], // سيتم حذفه
    ['occurred_at' => now()->subDays(89)], // سيبقى
  ]);

  // 4. تجهيز بيانات اختبار لجدول الـ audit_logs (سجلين قديمين وسجل جديد)
  DB::table('audit_logs')->insert([
    ['occurred_at' => now()->subDays(100)], // سيتم حذفه
    ['occurred_at' => now()->subDays(95)],  // سيتم حذفه
    ['occurred_at' => now()->subDays(10)],  // سيبقى
  ]);

  // 5. تشغيل الأمر ومراقبة المخرجات النصية المطبوعة بدقة
  $this->artisan('logs:cleanup')
    ->expectsOutputToContain('Deleted 1 logs')
    ->expectsOutputToContain('Deleted 2 audit logs')
    ->assertSuccessful();

  // 6. التأكد برمجياً أن السجلات القديمة حُذفت فعلاً من قاعدة البيانات
  expect(DB::table('logs')->count())->toBe(1)
    ->and(DB::table('audit_logs')->count())->toBe(1);
});
