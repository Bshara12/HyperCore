<?php

namespace Tests\Feature\Domains\Notifications\Jobs;

use Tests\TestCase;
use App\Domains\Notifications\Jobs\RetryFailedNotificationDeliveryJob;
use App\Domains\Notifications\Jobs\DispatchNotificationDeliveryJob;
use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class RetryFailedNotificationDeliveryJobTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    // 💡 التعديل هنا: استخدام ulid() ليتوافق تماماً مع حقيقة الموديل والبيانات المدخلة
    Schema::create('notification_deliveries', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('status');
      $table->timestamp('next_retry_at')->nullable();
      $table->integer('attempts')->default(0);
      $table->integer('max_attempts')->default(3);
      $table->timestamps();
    });
  }

  protected function tearDown(): void
  {
    Schema::dropIfExists('notification_deliveries');

    parent::tearDown();
  }

  // --------------------------------------------------------------------
  // 1. مسار النجاح والتخطي المبني على شروط الـ Loop وقيم الـ Attempts
  // --------------------------------------------------------------------
  public function test_dispatches_retry_jobs_for_due_failed_deliveries_within_max_attempts()
  {
    Queue::fake([DispatchNotificationDeliveryJob::class]);

    // 💡 نمرر معرّفات نصية رقمية عنوة هنا لإرضاء دالة chunkById في بيئة SQLite المؤقتة

    // 1. عملية فاشلة ومستحقة الإعادة الآن (يجب عمل Dispatch لها)
    $eligibleDelivery = NotificationDelivery::create([
      'id' => '1',
      'status' => DeliveryStatus::Failed,
      'next_retry_at' => now()->subMinutes(2),
      'attempts' => 1,
      'max_attempts' => 3,
    ]);

    // 2. عملية فاشلة ومستحقة الوقت لكنها استنفدت محاولاتها (يجب تخطيها بالـ continue)
    $exhaustedDelivery = NotificationDelivery::create([
      'id' => '2',
      'status' => DeliveryStatus::Failed,
      'next_retry_at' => now()->subMinutes(1),
      'attempts' => 3,
      'max_attempts' => 3,
    ]);

    // 3. عملية فاشلة ولكن وقت الإعادة في المستقبل (يجب تجاهلها بالـ Query)
    $futureDelivery = NotificationDelivery::create([
      'id' => '3',
      'status' => DeliveryStatus::Failed,
      'next_retry_at' => now()->addMinutes(30),
      'attempts' => 0,
      'max_attempts' => 3,
    ]);

    // 4. عملية ناجحة تماماً (يجب تجاهلها بالـ Query)
    $deliveredDelivery = NotificationDelivery::create([
      'id' => '4',
      'status' => DeliveryStatus::Delivered,
      'next_retry_at' => now()->subMinutes(10),
      'attempts' => 1,
      'max_attempts' => 3,
    ]);

    // تشغيل الـ Job الرئيسي
    $job = new RetryFailedNotificationDeliveryJob();
    $job->handle();

    // التأكد من إرسال العملية المؤهلة فقط للطابور لإعادة التنفيذ
    Queue::assertPushed(DispatchNotificationDeliveryJob::class, function ($pushedJob) use ($eligibleDelivery) {
      return (string) $pushedJob->deliveryId === (string) $eligibleDelivery->id;
    });

    // التأكد من عدم إرسال العملية المستنفدة للمحاولات
    Queue::assertNotPushed(DispatchNotificationDeliveryJob::class, function ($pushedJob) use ($exhaustedDelivery) {
      return (string) $pushedJob->deliveryId === (string) $exhaustedDelivery->id;
    });
  }

  // --------------------------------------------------------------------
  // 2. فحص إعدادات الـ Overlap المخصصة للـ Middleware
  // --------------------------------------------------------------------
  public function test_it_defines_correct_overlap_configuration_methods()
  {
    $job = new RetryFailedNotificationDeliveryJob();
    $reflection = new \ReflectionClass(RetryFailedNotificationDeliveryJob::class);

    // فحص الـ overlapKey
    $keyMethod = $reflection->getMethod('overlapKey');
    $keyMethod->setAccessible(true);
    $this->assertEquals('delivery-retry:sweep', $keyMethod->invoke($job));

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
