<?php

namespace Tests\Feature\Domains\Notifications\Models;

use App\Models\Domains\Notifications\Models\NotificationBatch;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

// محاكاة كلاس الـ Notification إذا لم يكن متوفراً في نفس الـ Namespace لتجنب التعارض أثناء الفحص
if (!class_exists(\App\Models\Domains\Notifications\Models\Notification::class)) {
  class_alias(\Illuminate\Database\Eloquent\Model::class, \App\Models\Domains\Notifications\Models\Notification::class);
}

beforeEach(function () {
  // 1. بناء جدول الدفعات وجدول الإشعارات المرتبط بها في الذاكرة
  Schema::create('notification_batches', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('project_id')->nullable();
    $table->string('created_by_type')->nullable();
    $table->string('created_by_id')->nullable();
    $table->string('correlation_id')->nullable();
    $table->string('causation_id')->nullable();
    $table->string('request_id')->nullable();
    $table->text('actor_snapshot')->nullable();
    $table->text('source_snapshot')->nullable();
    $table->text('audit_meta')->nullable();
    $table->string('source_service')->nullable();
    $table->string('source_event_type')->nullable();
    $table->string('audience_type')->nullable();
    $table->text('audience_query')->nullable();
    $table->text('payload')->nullable();
    $table->string('status');
    $table->string('dedupe_key')->nullable();
    $table->integer('total_targets')->default(0);
    $table->integer('processed_targets')->default(0);
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
  });

  Schema::create('notifications', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('batch_id');
    $table->string('status');
    $table->timestamps();
  });
});

afterEach(function () {
  Schema::dropIfExists('notifications');
  Schema::dropIfExists('notification_batches');
});

// --------------------------------------------------------------------
// الاختبار الأول: فحص الـ ULID والـ Fillable والـ Casts
// --------------------------------------------------------------------
it('correctly casts batch fields and generates a ULID on creation', function () {
  $batch = NotificationBatch::create([
    'status' => 'pending',
    'actor_snapshot' => ['user_id' => 1],
    'source_snapshot' => ['event' => 'order.created'],
    'audit_meta' => ['ip' => '127.0.0.1'],
    'audience_query' => ['country' => 'SY'],
    'payload' => ['message' => 'Hello World'],
    'total_targets' => '150',      // نص سيتحول إلى Integer
    'processed_targets' => '50',   // نص سيتحول إلى Integer
    'scheduled_at' => '2026-05-18 12:00:00',
    'started_at' => now(),
    'completed_at' => now(),
  ]);

  // التأكد من توليد الـ ULID تلقائياً
  expect($batch->id)->not->toBeNull()
    ->and(Str::isUlid($batch->id))->toBeTrue();

  // التأكد من سلامة الـ Casts المتنوعة (Arrays, Integers, Datetimes)
  expect($batch->actor_snapshot)->toBeArray()
    ->and($batch->actor_snapshot['user_id'])->toBe(1)
    ->and($batch->payload['message'])->toBe('Hello World')
    ->and($batch->total_targets)->toBe(150)
    ->and($batch->processed_targets)->toBe(50)
    ->and($batch->scheduled_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

// --------------------------------------------------------------------
// الاختبار الثاني: فحص الـ MassPrunable Query باستخدام الـ Time Travel
// --------------------------------------------------------------------
it('filters correctly inside the batch prunable query', function () {
  // 1. الانتقال بالزمن إلى الماضي (قبل 35 يوماً - أي أكثر من الـ 30 يوم المطلوبة)
  $this->travelTo(now()->subDays(35));

  // دفعة مستحقة للحذف: حالتها completed وقديمة
  $prunableCompleted = NotificationBatch::create([
    'status' => 'completed',
  ]);

  // دفعة مستحقة للحذف: حالتها failed وقديمة
  $prunableFailed = NotificationBatch::create([
    'status' => 'failed',
  ]);

  // دفعة ستبقى: قديمة جداً ولكن حالتها pending (حالة حساسة لا تحذف)
  $skipPending = NotificationBatch::create([
    'status' => 'pending',
  ]);

  // 2. العودة إلى الوقت الحالي (الحاضر)
  $this->travelBack();

  // دفعة ستبقى: حالتها completed ولكنها حديثة (أقل من 30 يوماً)
  $skipRecent = NotificationBatch::create([
    'status' => 'completed',
  ]);

  // جلب الـ Builder وتنفيذ استعلام الـ prunable
  $prunableIds = (new NotificationBatch())->prunable()->pluck('id')->toArray();

  // التحقق الفعلي من تصفية السجلات حسب شروط الدالة بدقة
  expect($prunableIds)->toContain($prunableCompleted->id)
    ->toContain($prunableFailed->id)
    ->not->toContain($skipPending->id)
    ->not->toContain($skipRecent->id);
});

// --------------------------------------------------------------------
// الاختبار الثالث: فحص علاقة الـ HasMany (notifications)
// --------------------------------------------------------------------
it('has a valid relationship with notifications', function () {
  $batch = NotificationBatch::create([
    'status' => 'processing',
  ]);

  // إنشاء إشعارات مرتبطة بهذه الدفعة
  \Illuminate\Support\Facades\DB::table('notifications')->insert([
    [
      'id' => (string) Str::ulid(),
      'batch_id' => $batch->id,
      'status' => 'sent',
      'created_at' => now(),
      'updated_at' => now(),
    ],
    [
      'id' => (string) Str::ulid(),
      'batch_id' => $batch->id,
      'status' => 'failed',
      'created_at' => now(),
      'updated_at' => now(),
    ]
  ]);

  // التحقق من العلاقة وجلب السجلات التابعة لها
  expect($batch->notifications)->toHaveCount(2)
    ->and($batch->notifications->first()->batch_id)->toBe($batch->id);
});
