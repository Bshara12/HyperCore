<?php

namespace Tests\Feature\Domains\Notifications\Channels;

use Tests\TestCase;
use App\Domains\Notifications\Channels\EmailChannelDriver;
use App\Domains\Notifications\Mail\NotificationMail;
use App\Domains\Notifications\Services\NotificationDeliveryService;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Exception;

class EmailChannelDriverTest extends TestCase
{
  private $deliveryServiceMock;
  private EmailChannelDriver $driver;

  protected function setUp(): void
  {
    parent::setUp();

    Schema::create('notifications', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->text('metadata')->nullable();
      $table->string('status');
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
    $this->driver = new EmailChannelDriver($this->deliveryServiceMock);
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
      'channel' => 'email',
      'status' => 'pending',
    ]);

    $this->deliveryServiceMock->shouldReceive('markFailed')
      ->once()
      ->with(
        Mockery::type(NotificationDelivery::class),
        'notification_missing',
        'Notification relation is missing.'
      );

    $this->driver->send($delivery);

    // تأكيد صريح لمنع ظهور تحذير Risky وحساب التغطية
    $this->assertTrue(true);
  }

  // --------------------------------------------------------------------
  // المسار 2: غياب البريد الإلكتروني من الـ Metadata
  // --------------------------------------------------------------------
  public function test_marks_delivery_as_skipped_if_recipient_email_is_missing_in_metadata()
  {
    $notification = Notification::create([
      'status' => 'pending',
      'metadata' => ['something_else' => 'value']
    ]);

    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
      'channel' => 'email',
      'status' => 'pending',
    ]);

    $this->deliveryServiceMock->shouldReceive('markSkipped')
      ->once()
      ->with(
        Mockery::type(NotificationDelivery::class),
        'Recipient email is missing.'
      );

    $this->driver->send($delivery);

    // تأكيد صريح لمنع ظهور تحذير Risky وحساب التغطية
    $this->assertTrue(true);
  }

  // --------------------------------------------------------------------
  // المسار 3: نجاح عملية الإرسال بالكامل
  // --------------------------------------------------------------------
  public function test_processes_and_sends_the_email_successfully()
  {
    Mail::fake();

    $notification = Notification::create([
      'status' => 'pending',
      'metadata' => ['email' => ['to' => 'feras@example.com']]
    ]);

    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
      'channel' => 'email',
      'status' => 'pending',
    ]);

    $this->deliveryServiceMock->shouldReceive('markQueued')->once()->with(Mockery::type(NotificationDelivery::class));
    $this->deliveryServiceMock->shouldReceive('markSent')->once()->with(Mockery::type(NotificationDelivery::class));
    $this->deliveryServiceMock->shouldReceive('markDelivered')->once()->with(Mockery::type(NotificationDelivery::class));

    $this->driver->send($delivery);

    Mail::assertSent(NotificationMail::class, function ($mail) {
      return $mail->hasTo('feras@example.com');
    });
  }

  // --------------------------------------------------------------------
  // المسار 4: فشل الإرسال وحساب الـ Backoff ديناميكياً
  // --------------------------------------------------------------------
  public function test_marks_delivery_as_failed_with_dynamic_backoff_on_exception()
  {
    $notification = Notification::create([
      'status' => 'pending',
      'metadata' => ['email' => ['to' => 'feras@example.com']]
    ]);

    $delivery = NotificationDelivery::create([
      'notification_id' => $notification->id,
      'channel' => 'email',
      'status' => 'pending',
      'attempts' => 2,
    ]);

    Mail::shouldReceive('to')->with('feras@example.com')->andThrow(new Exception('SMTP Connection Timeout'));

    $this->deliveryServiceMock->shouldReceive('markQueued')->once()->with(Mockery::type(NotificationDelivery::class));

    $this->deliveryServiceMock->shouldReceive('markFailed')
      ->once()
      ->with(
        Mockery::type(NotificationDelivery::class),
        'Exception',
        'SMTP Connection Timeout',
        15
      );

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('SMTP Connection Timeout');

    $this->driver->send($delivery);
  }
}
