<?php

namespace Tests\Feature\Domains\Notifications\Jobs;

use Tests\TestCase;
use App\Domains\Notifications\Jobs\DispatchDueScheduledNotificationsJob;
use App\Domains\Notifications\Jobs\DispatchNotificationDeliveryJob;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class DispatchDueScheduledNotificationsJobTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    // تعديل نوع الـ id ليكون ulid متوافق تماماً مع سلوك الموديل الفعلي
    Schema::create('notifications', function (Blueprint $table) {
      $table->ulid('id')->primary(); // التغيير هنا
      $table->string('status');
      $table->timestamp('scheduled_at')->nullable();
      $table->timestamp('queued_at')->nullable();
      $table->timestamps();
    });

    Schema::create('notification_deliveries', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('notification_id'); // تغيير النوع ليتطابق مع الـ ulid الخاص بالجدول الأساسي
      $table->string('channel');
      $table->timestamps();
    });
  }

  protected function tearDown(): void
  {
    Schema::dropIfExists('notification_deliveries');
    Schema::dropIfExists('notifications');

    parent::tearDown();
  }

  // --------------------------------------------------------------------
  // 1. مسار النجاح: معالجة الإشعارات المستحقة وعمل الـ Push للـ Jobs
  // --------------------------------------------------------------------
  public function test_dispatches_due_scheduled_notifications_and_ignores_database_channels()
  {
    Queue::fake([DispatchNotificationDeliveryJob::class]);

    // إشعار مستحق ومجدول في الماضي
    $dueNotification = Notification::create([
      'status' => NotificationStatus::Pending,
      'scheduled_at' => now()->subMinutes(5),
    ]);

    // قنوات توصيل تابعة للإشعار المستحق (قناة صالحة وقناة داتابيز)
    $validDelivery = NotificationDelivery::create([
      'id' => '01kry0pfkt04ah17j0dsyy360a',
      'notification_id' => $dueNotification->id,
      'channel' => 'webhook',
    ]);

    $dbDelivery = NotificationDelivery::create([
      'id' => '01kry0pfkt04ah17j0dsyy360b',
      'notification_id' => $dueNotification->id,
      'channel' => 'database',
    ]);

    // إشعار مجدول في المستقبل (يجب تجاهله)
    $futureNotification = Notification::create([
      'status' => NotificationStatus::Pending,
      'scheduled_at' => now()->addHours(2),
    ]);

    // إشعار مستحق ولكن حالته ليست Pending (يجب تجاهله)
    $alreadyQueuedNotification = Notification::create([
      'status' => NotificationStatus::Queued,
      'scheduled_at' => now()->subMinutes(10),
    ]);

    // تشغيل الـ Job
    $job = new DispatchDueScheduledNotificationsJob();
    $job->handle();

    // التأكد من تحديث حالة الإشعار المستحق فقط
    $dueNotification->refresh();
    $this->assertEquals(NotificationStatus::Queued, $dueNotification->status);
    $this->assertNotNull($dueNotification->queued_at);

    // التأكد من عدم تحديث أو المساس بالإشعارات الأخرى
    $futureNotification->refresh();
    $this->assertEquals(NotificationStatus::Pending, $futureNotification->status);

    // التعديل هنا: استخدام assertPushed لتتوافق مع الـ Queue Fake الخاص بلارايفل
    Queue::assertPushed(DispatchNotificationDeliveryJob::class, function ($job) use ($validDelivery) {
      return $job->deliveryId === $validDelivery->id;
    });

    Queue::assertNotPushed(DispatchNotificationDeliveryJob::class, function ($job) use ($dbDelivery) {
      return $job->deliveryId === $dbDelivery->id;
    });
  }

  // --------------------------------------------------------------------
  // 2. فحص إعدادات وتخصيص الـ Overlap لـ Middleware المنع
  // --------------------------------------------------------------------
  public function test_it_defines_correct_overlap_configuration_methods()
  {
    $job = new DispatchDueScheduledNotificationsJob();

    $reflection = new \ReflectionClass(DispatchDueScheduledNotificationsJob::class);

    // فحص الـ overlapKey
    $keyMethod = $reflection->getMethod('overlapKey');
    $keyMethod->setAccessible(true);
    $this->assertEquals('scheduled-notifications:sweep', $keyMethod->invoke($job));

    // فحص الـ overlapReleaseAfter
    $releaseMethod = $reflection->getMethod('overlapReleaseAfter');
    $releaseMethod->setAccessible(true);
    $this->assertEquals(30, $releaseMethod->invoke($job));

    // فحص الـ overlapExpireAfter
    $expireMethod = $reflection->getMethod('overlapExpireAfter');
    $expireMethod->setAccessible(true);
    $this->assertEquals(180, $expireMethod->invoke($job));
  }
}
