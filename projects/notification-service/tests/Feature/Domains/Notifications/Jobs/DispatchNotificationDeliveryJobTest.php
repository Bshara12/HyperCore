<?php

namespace Tests\Feature\Domains\Notifications\Jobs;

use Tests\TestCase;
use App\Domains\Notifications\Jobs\DispatchNotificationDeliveryJob;
use App\Domains\Notifications\Jobs\BroadcastNotificationJob;
use App\Domains\Notifications\Jobs\DispatchEmailNotificationJob;
use App\Domains\Notifications\Jobs\DispatchWebhookNotificationJob;
use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class DispatchNotificationDeliveryJobTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    Schema::create('notifications', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('status');
      $table->timestamp('delivered_at')->nullable();
      $table->timestamps();
    });

    Schema::create('notification_deliveries', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('notification_id')->nullable();
      $table->string('status');
      $table->string('channel');
      $table->integer('attempts')->default(0);
      $table->timestamp('sent_at')->nullable();
      $table->timestamp('delivered_at')->nullable();
      $table->timestamp('last_attempt_at')->nullable();
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
  // 1. مسار الخروج المبكر إذا كانت الحالة مستلمة بالفعل
  // --------------------------------------------------------------------
  public function test_returns_early_if_delivery_status_is_already_delivered()
  {
    Queue::fake();

    $delivery = NotificationDelivery::create([
      'status' => DeliveryStatus::Delivered,
      'channel' => NotificationChannel::Email->value,
    ]);

    $job = new DispatchNotificationDeliveryJob($delivery->id);
    $job->handle();

    // نتأكد أنه لم يتم إرسال أي Job فرعي للطابور
    Queue::assertNothingPushed();
  }

  // --------------------------------------------------------------------
  // 2. مسار التعامل مع قناة الـ Database مباشرة وتحديث السجلات
  // --------------------------------------------------------------------
  public function test_handles_database_channel_inline_and_updates_statuses()
  {
    $notification = Notification::create([
      'status' => NotificationStatus::Pending->value ?? 'pending',
    ]);

    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
      'status' => DeliveryStatus::Pending->value ?? 'pending',
      'channel' => NotificationChannel::Database->value,
      'attempts' => 0,
    ]);

    $job = new DispatchNotificationDeliveryJob($delivery->id);
    $job->handle();

    // فحص تحديث بيانات الـ Delivery
    $delivery->refresh();
    $this->assertEquals(DeliveryStatus::Delivered, $delivery->status);
    $this->assertEquals(1, $delivery->attempts);
    $this->assertNotNull($delivery->sent_at);
    $this->assertNotNull($delivery->delivered_at);
    $this->assertNotNull($delivery->last_attempt_at);

    // فحص تحديث بيانات الـ Notification المرتبطة
    $notification->refresh();
    $this->assertEquals(NotificationStatus::Delivered, $notification->status);
    $this->assertNotNull($notification->delivered_at);
  }

  // --------------------------------------------------------------------
  // 3. مسار قناة الـ Broadcast وتوجيهها للـ Job المخصص لها
  // --------------------------------------------------------------------
  public function test_dispatches_broadcast_notification_job_for_broadcast_channel()
  {
    Queue::fake([BroadcastNotificationJob::class]);

    $delivery = NotificationDelivery::create([
      'status' => DeliveryStatus::Pending->value ?? 'pending',
      'channel' => NotificationChannel::Broadcast->value,
    ]);

    $job = new DispatchNotificationDeliveryJob($delivery->id);
    $job->handle();

    Queue::assertPushed(BroadcastNotificationJob::class, function ($pushedJob) use ($delivery) {
      return $pushedJob->deliveryId === $delivery->id;
    });
  }

  // --------------------------------------------------------------------
  // 4. مسار قناة الـ Email وتوجيهها للـ Job المخصص لها
  // --------------------------------------------------------------------
  public function test_dispatches_email_notification_job_for_email_channel()
  {
    Queue::fake([DispatchEmailNotificationJob::class]);

    $delivery = NotificationDelivery::create([
      'status' => DeliveryStatus::Pending->value ?? 'pending',
      'channel' => NotificationChannel::Email->value,
    ]);

    $job = new DispatchNotificationDeliveryJob($delivery->id);
    $job->handle();

    Queue::assertPushed(DispatchEmailNotificationJob::class, function ($pushedJob) use ($delivery) {
      return $pushedJob->deliveryId === $delivery->id;
    });
  }

  // --------------------------------------------------------------------
  // 5. مسار قناة الـ Webhook وتوجيهها للـ Job المخصص لها
  // --------------------------------------------------------------------
  public function test_dispatches_webhook_notification_job_for_webhook_channel()
  {
    Queue::fake([DispatchWebhookNotificationJob::class]);

    $delivery = NotificationDelivery::create([
      'status' => DeliveryStatus::Pending->value ?? 'pending',
      'channel' => NotificationChannel::Webhook->value,
    ]);

    $job = new DispatchNotificationDeliveryJob($delivery->id);
    $job->handle();

    Queue::assertPushed(DispatchWebhookNotificationJob::class, function ($pushedJob) use ($delivery) {
      return $pushedJob->deliveryId === $delivery->id;
    });
  }

  // --------------------------------------------------------------------
  // 6. اختبار الـ Overlap Key الخاص بالـ Middleware لمنع التداخل
  // --------------------------------------------------------------------
  public function test_it_returns_correct_delivery_overlap_key()
  {
    $job = new DispatchNotificationDeliveryJob('delivery-id-789');

    $reflection = new \ReflectionClass(DispatchNotificationDeliveryJob::class);
    $method = $reflection->getMethod('overlapKey');
    $method->setAccessible(true);

    $key = $method->invoke($job);

    $this->assertEquals('delivery:delivery-id-789', $key);
  }
}
