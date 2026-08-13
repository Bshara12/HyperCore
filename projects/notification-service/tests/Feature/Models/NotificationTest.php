<?php

namespace Tests\Feature\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function it_casts_attributes_correctly()
  {
    $notification = Notification::create([
      'user_id' => 'user-123',
      'title'   => 'Test Title',
      'body'    => 'Test Body',
      'data'    => ['key' => 'value'],
      'channel' => NotificationChannel::IN_APP,
      'status'  => NotificationStatus::SENT,
      'read_at' => '2026-07-23 10:00:00',
      'sent_at' => '2026-07-23 10:00:00',
    ]);

    $this->assertIsArray($notification->data);
    $this->assertEquals(['key' => 'value'], $notification->data);
    $this->assertInstanceOf(NotificationChannel::class, $notification->channel);
    $this->assertInstanceOf(NotificationStatus::class, $notification->status);
    $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $notification->read_at);
    $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $notification->sent_at);
  }

  #[Test]
  public function it_can_filter_notifications_for_a_specific_user_using_scope()
  {
    Notification::create([
      'user_id' => 'user-1',
      'title'   => 'Title 1',
      'body'    => 'Body 1',
      'channel' => NotificationChannel::IN_APP,
      'status'  => NotificationStatus::SENT,
    ]);

    Notification::create([
      'user_id' => 'user-2',
      'title'   => 'Title 2',
      'body'    => 'Body 2',
      'channel' => NotificationChannel::IN_APP,
      'status'  => NotificationStatus::SENT,
    ]);

    $notifications = Notification::forUser('user-1')->get();

    $this->assertCount(1, $notifications);
    $this->assertEquals('user-1', $notifications->first()->user_id);
  }

  #[Test]
  public function it_can_filter_in_app_notifications_using_scope()
  {
    Notification::create([
      'user_id' => 'user-123',
      'title'   => 'In-App',
      'body'    => 'Body',
      'channel' => NotificationChannel::IN_APP,
      'status'  => NotificationStatus::SENT,
    ]);

    Notification::create([
      'user_id' => 'user-123',
      'title'   => 'Email',
      'body'    => 'Body',
      'channel' => NotificationChannel::EMAIL,
      'status'  => NotificationStatus::SENT,
    ]);

    $notifications = Notification::inApp()->get();

    $this->assertCount(1, $notifications);
    $this->assertEquals(NotificationChannel::IN_APP, $notifications->first()->channel);
  }

  #[Test]
  public function it_can_filter_unread_notifications_using_scope()
  {
    Notification::create([
      'user_id' => 'user-123',
      'title'   => 'Unread',
      'body'    => 'Body',
      'channel' => NotificationChannel::IN_APP,
      'status'  => NotificationStatus::SENT,
      'read_at' => null,
    ]);

    Notification::create([
      'user_id' => 'user-123',
      'title'   => 'Read',
      'body'    => 'Body',
      'channel' => NotificationChannel::IN_APP,
      'status'  => NotificationStatus::SENT,
      'read_at' => now(),
    ]);

    $notifications = Notification::unread()->get();

    $this->assertCount(1, $notifications);
    $this->assertNull($notifications->first()->read_at);
  }

  #[Test]
  public function it_can_filter_notifications_by_status_using_scope()
  {
    Notification::create([
      'user_id' => 'user-123',
      'title'   => 'Sent',
      'body'    => 'Body',
      'channel' => NotificationChannel::IN_APP,
      'status'  => NotificationStatus::SENT,
    ]);

    Notification::create([
      'user_id' => 'user-123',
      'title'   => 'Failed',
      'body'    => 'Body',
      'channel' => NotificationChannel::IN_APP,
      'status'  => NotificationStatus::FAILED,
    ]);

    $notifications = Notification::withStatus(NotificationStatus::SENT)->get();

    $this->assertCount(1, $notifications);
    $this->assertEquals(NotificationStatus::SENT, $notifications->first()->status);
  }

  #[Test]
  public function it_marks_notification_as_read_successfully()
  {
    $notification = Notification::create([
      'user_id' => 'user-123',
      'title'   => 'Test',
      'body'    => 'Body',
      'channel' => NotificationChannel::IN_APP,
      'status'  => NotificationStatus::SENT,
      'read_at' => null,
    ]);

    $this->assertFalse($notification->isRead());

    $result = $notification->markAsRead();

    $this->assertTrue($result);
    $this->assertTrue($notification->fresh()->isRead());
    $this->assertNotNull($notification->fresh()->read_at);
  }

  #[Test]
  public function it_does_not_update_read_at_if_already_read()
  {
    $notification = Notification::create([
      'user_id' => 'user-123',
      'title'   => 'Test',
      'body'    => 'Body',
      'channel' => NotificationChannel::IN_APP,
      'status'  => NotificationStatus::SENT,
      'read_at' => now(),
    ]);

    $result = $notification->markAsRead();

    $this->assertFalse($result);
  }

  #[Test]
  public function it_marks_notification_as_sent()
  {
    $notification = Notification::create([
      'user_id' => 'user-123',
      'title'   => 'Test',
      'body'    => 'Body',
      'channel' => NotificationChannel::EMAIL,
      'status'  => NotificationStatus::FAILED,
      'sent_at' => null,
    ]);

    $result = $notification->markAsSent();

    $this->assertTrue($result);
    $this->assertEquals(NotificationStatus::SENT, $notification->fresh()->status);
    $this->assertNotNull($notification->fresh()->sent_at);
  }

  #[Test]
  public function it_marks_notification_as_failed_with_error_message()
  {
    $notification = Notification::create([
      'user_id' => 'user-123',
      'title'   => 'Test',
      'body'    => 'Body',
      'channel' => NotificationChannel::EMAIL,
      'status'  => NotificationStatus::SENT,
      'error_message' => null,
    ]);

    $errorMessage = 'SMTP Connection Refused';
    $result = $notification->markAsFailed($errorMessage);

    $this->assertTrue($result);
    $this->assertEquals(NotificationStatus::FAILED, $notification->fresh()->status);
    $this->assertEquals($errorMessage, $notification->fresh()->error_message);
  }
}
