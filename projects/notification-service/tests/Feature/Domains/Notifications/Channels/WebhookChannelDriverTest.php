<?php

namespace Tests\Feature\Domains\Notifications\Channels;

use Tests\TestCase;
use App\Domains\Notifications\Channels\WebhookChannelDriver;
use App\Domains\Notifications\Services\NotificationDeliveryService;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Mockery;
use RuntimeException;
use Exception;

class WebhookChannelDriverTest extends TestCase
{
  private $deliveryServiceMock;
  private WebhookChannelDriver $driver;

  protected function setUp(): void
  {
    parent::setUp();

    Schema::create('notifications', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('project_id')->nullable();
      $table->string('recipient_type')->nullable();
      $table->string('recipient_id')->nullable();
      $table->string('source_type')->nullable();
      $table->string('source_service')->nullable();
      $table->string('source_id')->nullable();
      $table->string('title')->nullable();
      $table->text('body')->nullable();
      $table->text('data')->nullable();
      $table->text('metadata')->nullable();
      $table->string('status'); // السطر السحري الناقص الذي سيحل المشكلة!
      $table->timestamps();
    });

    Schema::create('notification_deliveries', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('notification_id')->nullable();
      $table->string('channel');
      $table->string('status');
      $table->integer('attempts')->default(0);
      $table->timestamps();
    });

    $this->deliveryServiceMock = Mockery::mock(NotificationDeliveryService::class);
    $this->driver = new WebhookChannelDriver($this->deliveryServiceMock);
  }

  protected function tearDown(): void
  {
    Schema::dropIfExists('notification_deliveries');
    Schema::dropIfExists('notifications');
    Mockery::close();

    parent::tearDown();
  }

  // --------------------------------------------------------------------
  // المسار 1: غياب علاقة الـ Notification
  // --------------------------------------------------------------------
  public function test_marks_delivery_as_failed_if_notification_relation_is_missing()
  {
    $delivery = NotificationDelivery::create([
      'channel' => 'webhook',
      'status' => 'pending',
    ]);

    $this->deliveryServiceMock->shouldReceive('markFailed')
      ->once()
      ->with(Mockery::type(NotificationDelivery::class), 'notification_missing', 'Notification relation is missing.');

    $this->driver->send($delivery);

    $this->assertTrue(true);
  }

  // --------------------------------------------------------------------
  // المسار 2: غياب الـ Webhook URL بالكامل (Skip Path)
  // --------------------------------------------------------------------
  public function test_marks_delivery_as_skipped_if_webhook_url_is_missing_everywhere()
  {
    $notification = Notification::create([
      'status' => 'pending',
      'metadata' => ['webhook' => ['headers' => []]]
    ]);

    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
      'channel' => 'webhook',
      'status' => 'pending',
    ]);

    Config::set('services.notification_webhook.url', null);

    $this->deliveryServiceMock->shouldReceive('markSkipped')
      ->once()
      ->with(Mockery::type(NotificationDelivery::class), 'Webhook URL is missing.');

    $this->driver->send($delivery);

    $this->assertTrue(true);
  }

  // --------------------------------------------------------------------
  // المسار 3: نجاح الإرسال وتوليد الـ HMAC Signature بنجاح
  // --------------------------------------------------------------------
  public function test_sends_webhook_successfully_with_hmac_signature_and_custom_headers()
  {
    Http::fake([
      'https://api.feras.com/callback' => Http::response(['success' => true], 200)
    ]);

    $notification = Notification::create([
      'project_id' => 'proj_01',
      'recipient_type' => 'User',
      'recipient_id' => '123',
      'title' => 'Test Webhook',
      'body' => 'Webhook body content',
      'status' => 'pending',
      'metadata' => [
        'webhook' => [
          'url' => 'https://api.feras.com/callback',
          'secret' => 'super-secret-key',
          'headers' => ['Custom-Header' => 'FerasDev']
        ]
      ]
    ]);

    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
      'channel' => 'webhook',
      'status' => 'pending',
    ]);

    $this->deliveryServiceMock->shouldReceive('markQueued')->once()->with(Mockery::type(NotificationDelivery::class));
    $this->deliveryServiceMock->shouldReceive('markSent')->once()->with(Mockery::type(NotificationDelivery::class));
    $this->deliveryServiceMock->shouldReceive('markDelivered')->once()->with(Mockery::type(NotificationDelivery::class));

    $this->driver->send($delivery);

    Http::assertSent(function ($request) use ($notification) {
      return $request->url() === 'https://api.feras.com/callback'
        && $request->hasHeader('Custom-Header', 'FerasDev')
        && $request->hasHeader('X-Webhook-Signature')
        && $request['notification_id'] === $notification->id;
    });
  }


  // --------------------------------------------------------------------
  // المسار 4: فشل السيرفر المستلم وعودة كود حالة فاشل (HTTP Error Code)
  // --------------------------------------------------------------------
  public function test_marks_delivery_as_failed_if_webhook_endpoint_returns_failed_status_code()
  {
    Http::fake([
      'https://api.feras.com/callback' => Http::response('Internal Server Error', 500)
    ]);

    $notification = Notification::create([
      'status' => 'pending',
      'metadata' => ['webhook' => ['url' => 'https://api.feras.com/callback']]
    ]);

    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
      'channel' => 'webhook',
      'status' => 'pending',
      'attempts' => 1,
    ]);

    // إضافة التوقعات المفقودة لتتوافق مع ترتيب الـ Driver الجديد
    $this->deliveryServiceMock->shouldReceive('markQueued')->once()->with(Mockery::type(NotificationDelivery::class));
    $this->deliveryServiceMock->shouldReceive('markSent')->once()->with(Mockery::type(NotificationDelivery::class));

    $this->deliveryServiceMock->shouldReceive('markFailed')
      ->once()
      ->with(
        Mockery::type(NotificationDelivery::class),
        'RequestException',
        Mockery::type('string'),
        10
      );

    $this->expectException(\Illuminate\Http\Client\RequestException::class);

    $this->driver->send($delivery);
  }

  // --------------------------------------------------------------------
  // المسار 5: حدوث خطأ كلي أو استثناء حقيقي أثناء الـ Request
  // --------------------------------------------------------------------
  public function test_marks_delivery_as_failed_and_rethrows_on_connection_exception()
  {
    Http::fake([
      '*' => function () {
        throw new Exception('Connection dropped completely.');
      }
    ]);

    $notification = Notification::create([
      'status' => 'pending',
      'metadata' => ['webhook' => ['url' => 'https://api.feras.com/callback']]
    ]);

    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
      'channel' => 'webhook',
      'status' => 'pending',
      'attempts' => 0,
    ]);

    // إضافة التوقعات المفقودة لتتوافق مع ترتيب الـ Driver الجديد هنا أيضاً
    $this->deliveryServiceMock->shouldReceive('markQueued')->once()->with(Mockery::type(NotificationDelivery::class));
    $this->deliveryServiceMock->shouldReceive('markSent')->once()->with(Mockery::type(NotificationDelivery::class));

    $this->deliveryServiceMock->shouldReceive('markFailed')
      ->once()
      ->with(
        Mockery::type(NotificationDelivery::class),
        'Exception',
        'Connection dropped completely.',
        5
      );

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Connection dropped completely.');

    $this->driver->send($delivery);
  }
}
