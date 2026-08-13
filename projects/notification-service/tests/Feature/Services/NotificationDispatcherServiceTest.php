<?php

namespace Tests\Unit\Services;

use App\Enums\NotificationChannel;
use App\Events\NotificationSentEvent;
use App\Mail\NotificationMail;
use App\Models\Notification;
use App\Services\NotificationDispatcherService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationDispatcherServiceTest extends TestCase
{
  protected NotificationDispatcherService $service;

  protected function setUp(): void
  {
    parent::setUp();
    $this->service = new NotificationDispatcherService();

    // استخدام Log::spy بدل fake لاقتفاء أثر الـ Logs بشكل صحيح
    Log::spy();
  }

  // ════════════════════════════════════════════════════════════
  // 1. اختبار البريد الإلكتروني
  // ════════════════════════════════════════════════════════════

  #[Test]
  public function it_sends_email_notification_successfully_and_marks_as_sent(): void
  {
    Mail::fake();

    /** @var Notification|\Mockery::MockInterface $notification */
    $notification = Mockery::mock(Notification::class)->makePartial();
    $notification->id = 1;
    $notification->channel = NotificationChannel::EMAIL;
    $notification->user_email = 'user@example.com';

    $notification->shouldReceive('markAsSent')->once();

    $this->service->dispatch($notification);

    Mail::assertSent(NotificationMail::class, function ($mail) use ($notification) {
      return $mail->hasTo('user@example.com') && $mail->notification === $notification;
    });

    Log::shouldHaveReceived('info')
      ->once()
      ->with('Email notification sent', Mockery::type('array'));
  }

  #[Test]
  public function it_marks_email_notification_as_failed_when_email_is_missing(): void
  {
    Mail::fake();

    /** @var Notification|\Mockery::MockInterface $notification */
    $notification = Mockery::mock(Notification::class)->makePartial();
    $notification->id = 2;
    $notification->channel = NotificationChannel::EMAIL;
    $notification->user_email = null;

    $notification->shouldReceive('markAsFailed')
      ->once()
      ->with('Email address is missing.');

    $notification->shouldNotReceive('markAsSent');

    $this->service->dispatch($notification);

    Mail::assertNothingSent();

    Log::shouldHaveReceived('error')
      ->once()
      ->with('Email notification failed: missing email', Mockery::type('array'));
  }

  // ════════════════════════════════════════════════════════════
  // 2. اختبار القناة الداخلية (IN_APP)
  // ════════════════════════════════════════════════════════════

  #[Test]
  public function it_processes_in_app_notification_and_marks_as_sent(): void
  {
    /** @var Notification|\Mockery::MockInterface $notification */
    $notification = Mockery::mock(Notification::class)->makePartial();
    $notification->id = 3;
    $notification->channel = NotificationChannel::IN_APP;

    $notification->shouldReceive('markAsSent')->once();

    $this->service->dispatch($notification);

    Log::shouldHaveReceived('info')
      ->once()
      ->with('In-app notification processed', Mockery::type('array'));
  }

  // ════════════════════════════════════════════════════════════
  // 3. اختبار القناة الفورية (REAL_TIME)
  // ════════════════════════════════════════════════════════════

  #[Test]
  public function it_broadcasts_real_time_notification_event_and_marks_as_sent(): void
  {
    Event::fake();

    /** @var Notification|\Mockery::MockInterface $notification */
    $notification = Mockery::mock(Notification::class)->makePartial();
    $notification->id = 4;
    $notification->user_id = 'user-999';
    $notification->channel = NotificationChannel::REAL_TIME;

    $notification->shouldReceive('markAsSent')->once();

    $this->service->dispatch($notification);

    Event::assertDispatched(NotificationSentEvent::class, function ($event) use ($notification) {
      return $event->notification === $notification;
    });

    Log::shouldHaveReceived('info')
      ->once()
      ->with('Real-time notification broadcast', Mockery::type('array'));
  }
}
