<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\InAppNotificationController;
use App\Models\Notification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InAppNotificationControllerTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function it_fetches_paginated_in_app_notifications_for_authenticated_user()
  {
    $userId = 'user-123';

    // إنشاء إشعارات داخلية للمستخدم الحالي مباشرة
    for ($i = 0; $i < 3; $i++) {
      Notification::create([
        'user_id' => $userId,
        'channel' => 'in_app',
        'title'   => "Test Title $i",
        'body'    => "Test Body $i",
      ]);
    }

    // إنشاء إشعار بقناة أخرى لن يتم جلبها
    Notification::create([
      'user_id' => $userId,
      'channel' => 'email',
      'title'   => 'Email Title',
      'body'    => 'Email Body',
    ]);

    // إنشاء إشعار لمستخدم آخر لن يتم جلبه
    Notification::create([
      'user_id' => 'other-user',
      'channel' => 'in_app',
      'title'   => 'Other Title',
      'body'    => 'Other Body',
    ]);

    $request = Request::create('/api/v1/in-app-notifications', 'GET');
    $request->attributes->set('authenticated_user', ['id' => $userId]);

    $controller = new InAppNotificationController();
    $response = $controller->index($request);
    $data = json_decode($response->getContent(), true);

    $this->assertTrue($data['success']);
    $this->assertCount(3, $data['data']['data']);
  }

  #[Test]
  public function it_returns_correct_unread_notifications_count()
  {
    $userId = 'user-123';

    // إشعاران غير مقروءة
    for ($i = 0; $i < 2; $i++) {
      Notification::create([
        'user_id' => $userId,
        'channel' => 'in_app',
        'title'   => 'Unread Title',
        'body'    => 'Unread Body',
        'read_at' => null,
      ]);
    }

    // إشعار مقروء مسبقاً
    Notification::create([
      'user_id' => $userId,
      'channel' => 'in_app',
      'title'   => 'Read Title',
      'body'    => 'Read Body',
      'read_at' => now(),
    ]);

    $request = Request::create('/api/v1/in-app-notifications/unread-count', 'GET');
    $request->attributes->set('authenticated_user', ['id' => $userId]);

    $controller = new InAppNotificationController();
    $response = $controller->unreadCount($request);
    $data = json_decode($response->getContent(), true);

    $this->assertTrue($data['success']);
    $this->assertEquals(2, $data['data']['unread_count']);
  }

  #[Test]
  public function it_marks_a_specific_notification_as_read()
  {
    $userId = 'user-123';
    $notification = Notification::create([
      'user_id' => $userId,
      'channel' => 'in_app',
      'title'   => 'Title',
      'body'    => 'Body',
      'read_at' => null,
    ]);

    $request = Request::create("/api/v1/in-app-notifications/{$notification->id}/read", 'PUT');
    $request->attributes->set('authenticated_user', ['id' => $userId]);

    $controller = new InAppNotificationController();
    $response = $controller->markAsRead($request, $notification->id);
    $data = json_decode($response->getContent(), true);

    $this->assertTrue($data['success']);
    $this->assertEquals('Notification marked as read.', $data['message']);

    $this->assertNotNull($notification->fresh()->read_at);
  }

  #[Test]
  public function it_fails_to_mark_non_existent_or_unauthorized_notification_as_read()
  {
    $this->expectException(ModelNotFoundException::class);

    $userId = 'user-123';
    $notification = Notification::create([
      'user_id' => 'other-user', // ملك مستخدم آخر
      'channel' => 'in_app',
      'title'   => 'Title',
      'body'    => 'Body',
    ]);

    $request = Request::create("/api/v1/in-app-notifications/{$notification->id}/read", 'PUT');
    $request->attributes->set('authenticated_user', ['id' => $userId]);

    $controller = new InAppNotificationController();
    $controller->markAsRead($request, $notification->id);
  }

  #[Test]
  public function it_marks_all_unread_notifications_as_read()
  {
    $userId = 'user-123';

    for ($i = 0; $i < 3; $i++) {
      Notification::create([
        'user_id' => $userId,
        'channel' => 'in_app',
        'title'   => 'Title',
        'body'    => 'Body',
        'read_at' => null,
      ]);
    }

    $request = Request::create('/api/v1/in-app-notifications/read-all', 'PUT');
    $request->attributes->set('authenticated_user', ['id' => $userId]);

    $controller = new InAppNotificationController();
    $response = $controller->markAllAsRead($request);
    $data = json_decode($response->getContent(), true);

    $this->assertTrue($data['success']);
    $this->assertEquals('All notifications marked as read.', $data['message']);

    // التحقق من أن جميع الإشعارات أصبحت مقروءة
    $unreadCount = Notification::forUser($userId)->inApp()->unread()->count();
    $this->assertEquals(0, $unreadCount);
  }

  #[Test]
  public function it_deletes_a_specific_notification()
  {
    $userId = 'user-123';
    $notification = Notification::create([
      'user_id' => $userId,
      'channel' => 'in_app',
      'title'   => 'Title',
      'body'    => 'Body',
    ]);

    $request = Request::create("/api/v1/in-app-notifications/{$notification->id}", 'DELETE');
    $request->attributes->set('authenticated_user', ['id' => $userId]);

    $controller = new InAppNotificationController();
    $response = $controller->destroy($request, $notification->id);
    $data = json_decode($response->getContent(), true);

    $this->assertTrue($data['success']);
    $this->assertEquals('Notification deleted.', $data['message']);

    $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
  }
}
