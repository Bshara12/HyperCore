<?php

namespace Tests\Feature\Domains\Notifications\Jobs;

use Tests\TestCase;
use App\Domains\Notifications\Jobs\DispatchWebhookNotificationJob;
use App\Domains\Notifications\Channels\WebhookChannelDriver;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Mockery;

class DispatchWebhookNotificationJobTest extends TestCase
{
  private $webhookDriverMock;

  protected function setUp(): void
  {
    parent::setUp();

    // بناء جداول الـ Memory DB المعزولة لبيئة فحص سريعة وعملية
    Schema::create('notifications', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->timestamps();
    });

    Schema::create('notification_deliveries', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('notification_id')->nullable();
      $table->timestamps();
    });

    // إنشاء Mock للـ Driver المخصص لمعالجة الـ Webhooks
    $this->webhookDriverMock = Mockery::mock(WebhookChannelDriver::class);
  }

  protected function tearDown(): void
  {
    Schema::dropIfExists('notification_deliveries');
    Schema::dropIfExists('notifications');
    Mockery::close();

    parent::tearDown();
  }

  // --------------------------------------------------------------------
  // 1. اختبار مسار النجاح وتمرير الـ Delivery مع علاقاتها للـ Driver
  // --------------------------------------------------------------------
  public function test_dispatches_webhook_notification_successfully_via_driver()
  {
    $notification = Notification::create();
    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
    ]);

    // نتوقع استدعاء دالة send لمرة واحدة، والتحقق من تحميل كائن الـ Notification بداخلها
    $this->webhookDriverMock->shouldReceive('send')
      ->once()
      ->with(Mockery::on(function ($argument) use ($delivery) {
        return $argument->id === $delivery->id && $argument->relationLoaded('notification');
      }));

    $job = new DispatchWebhookNotificationJob($delivery->id);
    $job->handle($this->webhookDriverMock);

    // تأكيد صريح لتجنب الـ WARN (Risky Test) الخاص بـ Pest
    $this->assertTrue(true);
  }

  // --------------------------------------------------------------------
  // 2. اختبار مفتاح الـ Overlap الخاص بـ Middleware التداخل
  // --------------------------------------------------------------------
  public function test_it_returns_correct_webhook_overlap_key()
  {
    $job = new DispatchWebhookNotificationJob('delivery-webhook-555');

    // استخدام الـ Reflection للوصول لـ الدالة المحمية (protected) واختبارها
    $reflection = new \ReflectionClass(DispatchWebhookNotificationJob::class);
    $method = $reflection->getMethod('overlapKey');
    $method->setAccessible(true);

    $key = $method->invoke($job);

    $this->assertEquals('webhook:delivery-webhook-555', $key);
  }
}
