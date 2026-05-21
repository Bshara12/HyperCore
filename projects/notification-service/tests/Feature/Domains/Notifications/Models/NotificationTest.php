<?php

namespace Tests\Feature\Domains\Notifications\Models;

use App\Models\Domains\Notifications\Models\Notification;
use App\Domains\Notifications\Enums\NotificationStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

// افتراض أسماء كلاسات العلاقات الوهمية إذا لم تكن منشأة بالكامل لتجنب أخطاء الـ PHP Namespace
if (!class_exists(\App\Models\Domains\Notifications\Models\NotificationDelivery::class)) {
  class_alias(\Illuminate\Database\Eloquent\Model::class, \App\Models\Domains\Notifications\Models\NotificationDelivery::class);
}
if (!class_exists(\App\Models\Domains\Notifications\Models\NotificationTemplate::class)) {
  class_alias(\Illuminate\Database\Eloquent\Model::class, \App\Models\Domains\Notifications\Models\NotificationTemplate::class);
}
if (!class_exists(\App\Models\Domains\Notifications\Models\NotificationBatch::class)) {
  class_alias(\Illuminate\Database\Eloquent\Model::class, \App\Models\Domains\Notifications\Models\NotificationBatch::class);
}

beforeEach(function () {
  // 1. بناء الجداول المطلوبة للاختبار داخل الذاكرة (SQLite :memory:)
  Schema::create('notifications', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('project_id')->nullable();
    $table->string('recipient_type')->nullable();
    $table->string('recipient_id')->nullable();
    $table->string('source_type')->nullable();
    $table->string('source_service')->nullable();
    $table->string('source_id')->nullable();
    $table->string('created_by_type')->nullable();
    $table->string('created_by_id')->nullable();
    $table->string('correlation_id')->nullable();
    $table->string('causation_id')->nullable();
    $table->string('request_id')->nullable();
    $table->text('actor_snapshot')->nullable();
    $table->text('source_snapshot')->nullable();
    $table->text('audit_meta')->nullable();
    $table->string('template_id')->nullable();
    $table->string('topic_key')->nullable();
    $table->string('title')->nullable();
    $table->text('body')->nullable();
    $table->text('data')->nullable();
    $table->text('metadata')->nullable();
    $table->integer('priority')->default(0);
    $table->string('status');
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('queued_at')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('read_at')->nullable();
    $table->string('dedupe_key')->nullable();
    $table->string('batch_id')->nullable();
    $table->timestamps();
  });

  Schema::create('notification_deliveries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('notification_id');
    $table->timestamps();
  });

  Schema::create('notification_templates', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->timestamps();
  });

  Schema::create('notification_batches', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->timestamps();
  });
});

afterEach(function () {
  Schema::dropIfExists('notification_deliveries');
  Schema::dropIfExists('notification_templates');
  Schema::dropIfExists('notification_batches');
  Schema::dropIfExists('notifications');
});

// --------------------------------------------------------------------
// الاختبار الأول: فحص الـ ULID والـ Fillable والـ Casts
// --------------------------------------------------------------------
it('correctly casts fields and generates a ULID on creation', function () {
  $notification = Notification::create([
    'title' => 'Test Notification',
    'status' => NotificationStatus::Pending, // اختبار الـ Enum Cast
    'actor_snapshot' => ['name' => 'Feras'], // اختبار الـ Array Cast
    'source_snapshot' => ['type' => 'system'],
    'audit_meta' => ['ip' => '127.0.0.1'],
    'data' => ['payload' => 'abc'],
    'metadata' => ['key' => 'value'],
    'priority' => '10', // سيتحول إلى Integer تلقائياً
    'scheduled_at' => '2026-05-18 12:00:00', // سيتحول إلى Datetime
    'queued_at' => now(),
    'sent_at' => now(),
    'delivered_at' => now(),
    'read_at' => now(),
  ]);

  // التأكد من توليد الـ ULID تلقائياً
  expect($notification->id)->not->toBeNull()
    ->and(Str::isUlid($notification->id))->toBeTrue();

  // التأكد من سلامة الـ Casts
  expect($notification->status)->toBeInstanceOf(NotificationStatus::class)
    ->and($notification->status)->toBe(NotificationStatus::Pending)
    ->and($notification->actor_snapshot)->toBeArray()
    ->and($notification->actor_snapshot['name'])->toBe('Feras')
    ->and($notification->priority)->toBe(10)
    ->and($notification->scheduled_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

// --------------------------------------------------------------------
// الاختبار الثاني: فحص الـ MassPrunable Query الشروط بدقة
// --------------------------------------------------------------------
it('filters correctly inside the prunable query', function () {
  // 1. السفر عبر الزمن إلى الماضي (قبل 95 يوماً من الآن) لإنشاء السجلات القديمة
  $this->travelTo(now()->subDays(95));

  // سجل مستحق للحذف: حالته Read وقديم
  $prunable1 = Notification::create([
    'status' => NotificationStatus::Read,
  ]);

  // سجل مستحق للحذف: حالته Failed وقديم
  $prunable2 = Notification::create([
    'status' => NotificationStatus::Failed,
  ]);

  // سجل سيبقى: قديم جداً ولكن حالته Pending (حالة حساسة لا تحذف)
  $skipPending = Notification::create([
    'status' => NotificationStatus::Pending,
  ]);

  // 2. العودة إلى الوقت الحالي (الحاضر)
  $this->travelBack();

  // 3. إنشاء سجل في الوقت الحالي (حديث - أقل من 90 يوم)
  $skipRecent = Notification::create([
    'status' => NotificationStatus::Read,
  ]);

  // جلب الـ Builder الخاص بالـ prunable وتنفيذ الاستعلام
  $prunableIds = (new Notification())->prunable()->pluck('id')->toArray();

  // التحقق من أن السجلات التي أنشئت في الماضي المستحقة فقط هي التي تم التقاطها
  expect($prunableIds)->toContain($prunable1->id)
    ->toContain($prunable2->id)
    ->not->toContain($skipRecent->id)
    ->not->toContain($skipPending->id);
});

// --------------------------------------------------------------------
// الاختبار الثالث: فحص العلاقات الـ Relationships
// --------------------------------------------------------------------
it('has valid relationships with deliveries, template, and batch', function () {
  // تجهيز السجلات المرتبطة بالحقول الأجنبية (Foreign Keys)
  $templateId = (string) Str::ulid();
  $batchId = (string) Str::ulid();

  \Illuminate\Support\Facades\DB::table('notification_templates')->insert(['id' => $templateId]);
  \Illuminate\Support\Facades\DB::table('notification_batches')->insert(['id' => $batchId]);

  $notification = Notification::create([
    'status' => NotificationStatus::Pending,
    'template_id' => $templateId,
    'batch_id' => $batchId,
  ]);

  // ربط سجل في جدول الـ deliveries
  \Illuminate\Support\Facades\DB::table('notification_deliveries')->insert([
    'notification_id' => $notification->id,
  ]);

  // فحص علاقة HasMany (deliveries)
  expect($notification->deliveries)->toHaveCount(1)
    ->and($notification->deliveries->first()->notification_id)->toBe($notification->id);

  // فحص علاقة BelongsTo (template)
  expect($notification->template)->not->toBeNull()
    ->and($notification->template->id)->toBe($templateId);

  // فحص علاقة BelongsTo (batch)
  expect($notification->batch)->not->toBeNull()
    ->and($notification->batch->id)->toBe($batchId);
});
