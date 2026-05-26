<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationBatch;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\DeliveryStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Console\Command;

beforeEach(function () {
  // 1. بناء الجداول في الذاكرة لفحص عملية الـ Pruning الحقيقية
  Schema::create('notifications', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('status');
    $table->timestamps();
  });

  Schema::create('notification_deliveries', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('notification_id');
    $table->string('channel');
    $table->string('status');
    $table->timestamps();
  });

  Schema::create('notification_batches', function (Blueprint $table) {
    $table->ulid('id')->primary();
    $table->string('status');
    $table->timestamps();
  });
});

afterEach(function () {
  Schema::dropIfExists('notification_deliveries');
  Schema::dropIfExists('notification_batches');
  Schema::dropIfExists('notifications');
});

// --------------------------------------------------------------------
// الاختبار: التحقق من حذف البيانات القديمة وطباعة الإحصائيات بدقة
// --------------------------------------------------------------------
it('prunes old notification data and displays correct counts', function () {

  // 1. السفر عبر الزمن إلى الماضي البعيد لإنشاء سجلات مستحقة للحذف
  // الإشعارات تحتاج لأكثر من 90 يوم، والدفعات ومحاولات الإرسال تحتاج لأكثر من 30 يوم
  $this->travelTo(now()->subDays(100));

  // إنشاء سجلين قابلين للحذف في جدول الإشعارات
  Notification::create(['status' => NotificationStatus::Read]);
  Notification::create(['status' => NotificationStatus::Failed]);

  // إنشاء سجل قابل للحذف في جدول محاولات التوصيل
  NotificationDelivery::create([
    'notification_id' => (string) \Illuminate\Support\Str::ulid(),
    'channel' => 'sms',
    'status' => DeliveryStatus::Sent
  ]);

  // إنشاء سجل قابل للحذف في جدول الدفعات
  NotificationBatch::create(['status' => 'completed']);

  // 2. العودة إلى الحاضر
  $this->travelBack();

  // إنشاء سجلات حديثة (لا يجب حذفها) للتأكد من دقة الأرقام
  Notification::create(['status' => NotificationStatus::Read]); // حديث، لن يحذف

  // 3. تشغيل الـ Command والتحقق من النصوص المخرجة وكود النجاح
  $this->artisan('notifications:prune')
    ->expectsOutput('Pruned notifications: 2') // يتوقع حذف سجلين
    ->expectsOutput('Pruned deliveries: 1')    // يتوقع حذف سجل واحد
    ->expectsOutput('Pruned batches: 1')       // يتوقع حذف سجل واحد
    ->assertExitCode(Command::SUCCESS);

  // 4. تأكيد إضافي: التحقق من أن قاعدة البيانات فارغة من السجلات القديمة
  expect(Notification::count())->toBe(1) // السجل الحديث فقط هو المتبقي
    ->and(NotificationDelivery::count())->toBe(0)
    ->and(NotificationBatch::count())->toBe(0);
});
