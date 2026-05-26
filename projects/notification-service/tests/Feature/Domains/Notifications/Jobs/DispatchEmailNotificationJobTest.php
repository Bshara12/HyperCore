<?php

namespace Tests\Feature\Domains\Notifications\Jobs;

use Tests\TestCase;
use App\Domains\Notifications\Jobs\DispatchEmailNotificationJob;
use App\Domains\Notifications\Channels\EmailChannelDriver;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Mockery;

class DispatchEmailNotificationJobTest extends TestCase
{
  private $emailDriverMock;

  protected function setUp(): void
  {
    parent::setUp();

    // بناء جداول الميموري لتأمين بيئة اختبار معزولة وسريعة
    Schema::create('notifications', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->timestamps();
    });

    Schema::create('notification_deliveries', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('notification_id')->nullable();
      $table->timestamps();
    });

    // إنشاء Mock للـ Driver المسؤل عن الإرسال
    $this->emailDriverMock = Mockery::mock(EmailChannelDriver::class);
  }

  protected function tearDown(): void
  {
    Schema::dropIfExists('notification_deliveries');
    Schema::dropIfExists('notifications');
    Mockery::close();

    parent::tearDown();
  }

  // --------------------------------------------------------------------
  // 1. اختبار مسار النجاح واستدعاء دالة الإرسال داخل الـ Driver
  // --------------------------------------------------------------------
  public function test_dispatches_email_notification_successfully_via_driver()
  {
    $notification = Notification::create();
    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
    ]);

    // نتوقع أن يقوم الـ Job بتمرير كائن الـ Delivery للـ Driver لإتمام الإرسال
    $this->emailDriverMock->shouldReceive('send')
      ->once()
      ->with(Mockery::on(function ($argument) use ($delivery) {
        return $argument->id === $delivery->id && $argument->relationLoaded('notification');
      }));

    $job = new DispatchEmailNotificationJob($delivery->id);
    $job->handle($this->emailDriverMock);

    // 💡 التعديل هنا: إضافة تأكيد صريح لإلغاء الـ WARN وجعل الاختبار أخضر بالكامل
    $this->assertTrue(true);
  }

  // --------------------------------------------------------------------
  // 2. اختبار الـ Overlap Key لـ Middleware منع التداخل
  // --------------------------------------------------------------------
  public function test_it_returns_correct_email_overlap_key()
  {
    $job = new DispatchEmailNotificationJob('delivery-email-123');

    // استخدام الـ Reflection لقراءة الدالة المحمية (protected)
    $reflection = new \ReflectionClass(DispatchEmailNotificationJob::class);
    $method = $reflection->getMethod('overlapKey');
    $method->setAccessible(true);

    $key = $method->invoke($job);

    $this->assertEquals('email:delivery-email-123', $key);
  }
}
