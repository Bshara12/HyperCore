<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogController;

beforeEach(function () {
  // 1. إنشاء الجداول المؤقتة لاختبار جلب البيانات والترقيم
  Schema::create('logs', function (Blueprint $table) {
    $table->id();
    $table->string('module');
    $table->integer('user_id');
    $table->string('event_type');
    $table->timestamp('occurred_at');
  });

  Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->timestamp('occurred_at');
  });

  // 2. تسجيل مسارات مؤقتة (Routes) لغايات فحص الـ Controller مباشرة
  Route::get('/test-logs', [LogController::class, 'index']);
  Route::get('/test-audit-logs', [LogController::class, 'auditLogs']);
});

afterEach(function () {
  Schema::dropIfExists('logs');
  Schema::dropIfExists('audit_logs');
});

it('returns paginated logs without filters', function () {
  // إدخال 12 سجل لفحص أن الـ Pagination يعمل (10 في الصفحة الأولى)
  for ($i = 1; $i <= 12; $i++) {
    DB::table('logs')->insert([
      'module' => 'auth',
      'user_id' => 1,
      'event_type' => 'login',
      'occurred_at' => now()->subMinutes($i)
    ]);
  }

  $response = $this->getJson('/test-logs');

  $response->assertStatus(200)
    ->assertJsonPath('total', 12)
    ->assertJsonCount(10, 'data'); // الصفحة الأولى تحتوي على 10 عناصر فقط
});

it('filters logs by module, user_id, event_type, and date range', function () {
  // إدخال سجلات مختلفة لتغطية شروط الـ if بالكامل
  DB::table('logs')->insert([
    [
      'module' => 'users',
      'user_id' => 1,
      'event_type' => 'create',
      'occurred_at' => '2026-05-10 10:00:00'
    ],
    [
      'module' => 'billing',
      'user_id' => 2,
      'event_type' => 'payment',
      'occurred_at' => '2026-05-15 10:00:00'
    ],
  ]);

  // اختبار كل الفلاتر مجتمعة في الطلب (Request)
  $response = $this->getJson('/test-logs?' . http_build_query([
    'module' => 'users',
    'user_id' => 1,
    'event_type' => 'create',
    'from' => '2026-05-09 00:00:00',
    'to' => '2026-05-11 00:00:00'
  ]));

  $response->assertStatus(200)
    ->assertJsonPath('total', 1)
    ->assertJsonPath('data.0.module', 'users');
});

it('returns latest 50 audit logs', function () {
  // إدخال 55 سجل تدقيق لفحص الـ limit(50)
  for ($i = 1; $i <= 55; $i++) {
    DB::table('audit_logs')->insert([
      'occurred_at' => now()->subMinutes($i)
    ]);
  }

  $response = $this->getJson('/test-audit-logs');

  // الـ auditLogs تعيد Collection (مصفوفة مباشرة بدون تغليف data للـ Paginator)
  $response->assertStatus(200)
    ->assertJsonCount(50);
});
