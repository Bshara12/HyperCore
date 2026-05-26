<?php

namespace Tests\Feature\Domains\Notifications\Models;

use App\Models\Domains\Notifications\Models\NotificationDelivery;
use App\Domains\Notifications\Enums\DeliveryStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

// محاكاة كلاس الـ Notification لمنع أي تعارض في البيئة المعزولة في حال عدم وجوده بذات الـ Namespace
if (!class_exists(\App\Models\Domains\Notifications\Models\Notification::class)) {
  class_alias(\Illuminate\Database\Eloquent\Model::class, \App\Models\Domains\Notifications\Models\Notification::class);
}

beforeEach(function () {
  // 1. بناء جدول محاولات التوصيل وجدول الإشعارات المرتبطة بها في الذاكرة
  Schema::create('notifications', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('status');
    $table->timestamps();
  });

  Schema::create('notification_deliveries', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('notification_id');
    $table->string('channel');
    $table->string('provider')->nullable();
    $table->string('status');
    $table->integer('attempts')->default(0);
    $table->integer('max_attempts')->default(3);
    $table->timestamp('last_attempt_at')->nullable();
    $table->timestamp('next_retry_at')->nullable();
    $table->string('provider_message_id')->nullable();
    $table->text('payload_snapshot')->nullable();
    $table->string('error_code')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamps();
  });
});

afterEach(function () {
  Schema::dropIfExists('notification_deliveries');
  Schema::dropIfExists('notifications');
});

// --------------------------------------------------------------------
// الاختبار الأول: فحص الـ ULID والـ Fillable والـ Casts والـ Enum
// --------------------------------------------------------------------
it('correctly casts delivery fields and generates a ULID on creation', function () {
  $delivery = NotificationDelivery::create([
    'notification_id' => (string) Str::ulid(),
    'channel' => 'sms',
    'provider' => 'twilio',
    'status' => DeliveryStatus::Sent, // فحص الـ Enum Cast
    'attempts' => '2',                // نص سيتحول تلقائياً إلى Integer
    'max_attempts' => '5',            // نص سيتحول تلقائياً إلى Integer
    'last_attempt_at' => now(),
    'next_retry_at' => '2026-05-18 20:00:00',
    'payload_snapshot' => ['to' => '+123456789', 'body' => 'Verification Code'],
    'sent_at' => now(),
    'delivered_at' => now(),
  ]);

  // التأكد من توليد المعرف الفريد ULID تلقائياً عند الحفظ
  expect($delivery->id)->not->toBeNull()
    ->and(Str::isUlid($delivery->id))->toBeTrue();

  // التأكد من مطابقة الـ Casts والـ Types بشكل دقيق
  expect($delivery->status)->toBeInstanceOf(DeliveryStatus::class)
    ->and($delivery->status)->toBe(DeliveryStatus::Sent)
    ->and($delivery->attempts)->toBe(2)
    ->and($delivery->max_attempts)->toBe(5)
    ->and($delivery->payload_snapshot)->toBeArray()
    ->and($delivery->payload_snapshot['to'])->toBe('+123456789')
    ->and($delivery->next_retry_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

// --------------------------------------------------------------------
// الاختبار الثاني: فحص الـ MassPrunable Query الشامل عبر الـ Time Travel
// --------------------------------------------------------------------
it('filters correctly inside the delivery prunable query', function () {
  // 1. الانتقال بالزمن إلى الماضي (قبل 35 يوماً - تخطي الـ 30 يوماً المشروطة)
  $this->travelTo(now()->subDays(35));

  // سجل محاولة توصيل قديم (مستحق للحذف بناءً على التاريخ فقط دون النظر للحالة)
  $prunableDelivery = NotificationDelivery::create([
    'notification_id' => (string) Str::ulid(),
    'channel' => 'email',
    'status' => DeliveryStatus::Failed,
  ]);

  // 2. العودة الفورية إلى الوقت الحالي (الحاضر)
  $this->travelBack();

  // سجل حديث العهد (أقل من 30 يوماً - لا يجب حذفه)
  $skipRecentDelivery = NotificationDelivery::create([
    'notification_id' => (string) Str::ulid(),
    'channel' => 'email',
    'status' => DeliveryStatus::Delivered,
  ]);

  // تنفيذ استعلام دالة الـ prunable وجلب المعرفات
  $prunableIds = (new NotificationDelivery())->prunable()->pluck('id')->toArray();

  // التحقق الفعلي من التقاط السجل القديم حصراً وتخطي الحديث
  expect($prunableIds)->toContain($prunableDelivery->id)
    ->not->toContain($skipRecentDelivery->id);
});

// --------------------------------------------------------------------
// الاختبار الثالث: فحص علاقة الـ BelongsTo (notification)
// --------------------------------------------------------------------
it('has a valid belongsTo relationship with notification', function () {
  $notificationId = (string) Str::ulid();

  // إنشاء سجل الإشعار الأب داخل جدول الاستهداف
  \Illuminate\Support\Facades\DB::table('notifications')->insert([
    'id' => $notificationId,
    'status' => 'pending',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  // إنشاء سجل محاولة التوصيل وربطه بالإشعار الأب
  $delivery = NotificationDelivery::create([
    'notification_id' => $notificationId,
    'channel' => 'push',
    'status' => DeliveryStatus::Pending,
  ]);

  // الفحص البرمجي للعلاقة والتأكد من جلب الموديل الأب بشكل صحيح
  expect($delivery->notification)->not->toBeNull()
    ->and($delivery->notification->id)->toBe($notificationId);
});
