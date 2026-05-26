<?php

namespace Tests\Feature\Domains\Notifications\Models;

use App\Models\Domains\Notifications\Models\NotificationServiceClient;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

beforeEach(function () {
  // 1. إنشاء جدول عملاء الخدمة في الذاكرة لتشغيل الاختبار بشكل معزول وسريع
  Schema::create('notification_service_clients', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('service_name');
    $table->string('token_hash');
    $table->text('scopes')->nullable();
    $table->text('allowed_projects')->nullable();
    $table->boolean('active')->default(true);
    $table->timestamp('last_used_at')->nullable();
    $table->timestamps();
  });
});

afterEach(function () {
  Schema::dropIfExists('notification_service_clients');
});

// --------------------------------------------------------------------
// الاختبار: فحص الـ ULID والـ Fillable والـ Casts بالكامل
// --------------------------------------------------------------------
it('correctly casts service client fields and generates a ULID on creation', function () {
  $client = NotificationServiceClient::create([
    'service_name' => 'billing_service',
    'token_hash' => hash('sha256', 'secret-token'),
    'scopes' => ['notifications:send', 'notifications:read'], // فحص الـ Array Cast للـ Scopes
    'allowed_projects' => ['proj_01', 'proj_02'],            // فحص الـ Array Cast للمشاريع
    'active' => '1',                                          // نص سيتحول تلقائياً إلى Boolean
    'last_used_at' => '2026-05-18 18:00:00',                  // نص سيتحول تلقائياً إلى Datetime
  ]);

  // 1. التحقق من توليد المعرف الفريد ULID تلقائياً عند الحفظ وثبات بنيته
  expect($client->id)->not->toBeNull()
    ->and(Str::isUlid($client->id))->toBeTrue();

  // 2. التحقق من سلامة الـ Boolean Cast
  expect($client->active)->toBeTrue();

  // 3. التحقق من سلامة الـ Datetime Cast بصيغة صحيحة خالية من الأخطاء المطبعية
  expect($client->last_used_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
    ->and($client->last_used_at->format('Y-m-d H:i:s'))->toBe('2026-05-18 18:00:00');

  // 4. التحقق من سلامة الـ Array Cast للحقول التي تخزن كـ JSON
  expect($client->scopes)->toBeArray()
    ->toHaveCount(2)
    ->toContain('notifications:send')
    ->and($client->allowed_projects)->toBeArray()
    ->toContain('proj_02');

  // 5. التحقق من الحقول النصية للتأكد من الـ Fillable بالكامل
  expect($client->service_name)->toBe('billing_service')
    ->and($client->token_hash)->not->toBeNull();
});
