<?php

namespace Tests\Feature\Domains\Notifications\Jobs;

use Tests\TestCase;
use App\Domains\Notifications\Jobs\BroadcastNotificationJob;
use App\Domains\Notifications\Services\NotificationDeliveryService;
use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Events\NotificationCreated;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use RuntimeException;
use Exception;

class BroadcastNotificationJobTest extends TestCase
{
  private $deliveryServiceMock;

  protected function setUp(): void
  {
    parent::setUp();

    // إنشاء جداول SQLite المؤقتة للاختبار
    Schema::create('notifications', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->timestamps();
    });

    Schema::create('notification_deliveries', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('notification_id')->nullable();
      $table->string('status');
      $table->integer('attempts')->default(0);
      $table->timestamps();
    });

    $this->deliveryServiceMock = Mockery::mock(NotificationDeliveryService::class);
  }

  protected function tearDown(): void
  {
    Schema::dropIfExists('notification_deliveries');
    Schema::dropIfExists('notifications');
    Mockery::close();

    parent::tearDown();
  }

  // --------------------------------------------------------------------
  // 1. مسار النجاح الكامل وإرسال البث
  // --------------------------------------------------------------------
  public function test_broadcasts_notification_successfully_and_fires_event()
  {
    Event::fake([NotificationCreated::class]);

    $notification = Notification::create();
    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
      'status' => 'pending', // أو استخدام DeliveryStatus::Pending إذا كان Enum مدعوم في الكاست
    ]);

    // ترتيب استدعاءات الـ Service المتوقعة
    $this->deliveryServiceMock->shouldReceive('markQueued')->once()->with(Mockery::type(NotificationDelivery::class));
    $this->deliveryServiceMock->shouldReceive('markSent')->once()->with(Mockery::type(NotificationDelivery::class));
    $this->deliveryServiceMock->shouldReceive('markDelivered')->once()->with(Mockery::type(NotificationDelivery::class));

    $job = new BroadcastNotificationJob($delivery->id);
    $job->handle($this->deliveryServiceMock);

    // التأكد من إطلاق الـ Event الخاص بالـ Broadcast
    Event::assertDispatched(NotificationCreated::class, function ($event) use ($notification) {
      return $event->notification->id === $notification->id;
    });
  }

  // --------------------------------------------------------------------
  // 2. مسار الخروج المبكر إذا كانت الحالة Delivered بالفعل
  // --------------------------------------------------------------------
  public function test_returns_early_if_delivery_status_is_already_delivered()
  {
    Event::fake();

    $notification = Notification::create();
    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
      'status' => DeliveryStatus::Delivered, // الخروج المبكر مشروط بـ Delivered
    ]);

    // نؤكد أن الـ Service لن تستدعي أي تحديث حالة لأن الـ Job سيخرج فوراً
    $this->deliveryServiceMock->shouldNotReceive('markQueued');

    $job = new BroadcastNotificationJob($delivery->id);
    $job->handle($this->deliveryServiceMock);

    Event::assertNotDispatched(NotificationCreated::class);
  }

  // --------------------------------------------------------------------
  // 3. مسار الفشل عند غياب علاقة الـ Notification
  // --------------------------------------------------------------------
  public function test_marks_as_failed_and_rethrows_if_notification_relation_is_missing()
  {
    Event::fake();

    // إنشاء Delivery بدون ربطه بـ Notification لضرب الـ if
    $delivery = NotificationDelivery::create([
      'notification_id' => null,
      'status' => 'pending',
      'attempts' => 1,
    ]);

    $this->deliveryServiceMock->shouldReceive('markQueued')->once()->with(Mockery::type(NotificationDelivery::class));

    // نتوقع دخول الـ catch وتسجيل الفشل عبر الـ Service بحساب الـ backoff (5 * 2 = 10)
    $this->deliveryServiceMock->shouldReceive('markFailed')
      ->once()
      ->with(
        Mockery::type(NotificationDelivery::class),
        'RuntimeException',
        'Notification relation is missing.',
        10
      );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Notification relation is missing.');

    $job = new BroadcastNotificationJob($delivery->id);
    $job->handle($this->deliveryServiceMock);
  }

  // --------------------------------------------------------------------
  // 4. اختبار الـ Overlap Key الخاص بالـ Middleware
  // --------------------------------------------------------------------
  public function test_it_returns_correct_overlap_key()
  {
    $job = new BroadcastNotificationJob('test-delivery-id-123');

    // استخدام الـ Reflection للوصول إلى الدالة المحمية (protected) واختبارها
    $reflection = new \ReflectionClass(BroadcastNotificationJob::class);
    $method = $reflection->getMethod('overlapKey');
    $method->setAccessible(true);

    $key = $method->invoke($job);

    $this->assertEquals('broadcast:test-delivery-id-123', $key);
  }
}
