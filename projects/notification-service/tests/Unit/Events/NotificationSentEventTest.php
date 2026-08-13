<?php

namespace Tests\Unit\Events;

use App\Events\NotificationSentEvent;
use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationSentEventTest extends TestCase
{
  #[Test]
  public function it_returns_correct_broadcast_channels()
  {
    // تجهيز بيانات وهمية للإشعار
    $notification = new Notification([
      'user_id' => 123,
      'title'   => 'Test Title',
      'body'    => 'Test Body',
      'data'    => ['key' => 'value'],
    ]);
    $notification->id = 1;

    $event = new NotificationSentEvent($notification);
    $channels = $event->broadcastOn();

    // التحقق من أن القناة هي قناة خاصة وللمستخدم الصحيح
    $this->assertCount(1, $channels);
    $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    $this->assertEquals('private-user.123', $channels[0]->name);
  }

  #[Test]
  public function it_returns_correct_broadcast_as_name()
  {
    // التحقق من اسم الحدث المخصص للـ Frontend
    $notification = new Notification();
    $event = new NotificationSentEvent($notification);

    $this->assertEquals('notification.sent', $event->broadcastAs());
  }

  #[Test]
  public function it_returns_correct_broadcast_payload_data()
  {
    // تجهيز بيانات الإشعار مع تاريخ الإنشاء
    $now = now();
    $notification = new Notification([
      'user_id' => 456,
      'title'   => 'Welcome Title',
      'body'    => 'Welcome Body',
      'data'    => ['action' => 'login'],
    ]);
    $notification->id = 10;
    $notification->created_at = $now;

    $event = new NotificationSentEvent($notification);
    $data = $event->broadcastWith();

    // التحقق من أن البيانات المُرسلة تطابق الهيكل المطلوب
    $this->assertEquals([
      'id'         => 10,
      'title'      => 'Welcome Title',
      'body'       => 'Welcome Body',
      'data'       => ['action' => 'login'],
      'created_at' => $now->toIso8601String(),
    ], $data);
  }
}
