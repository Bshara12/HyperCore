<?php

namespace Tests\Feature\Http\Controllers\Api;

use App\Http\Controllers\Api\NotificationController;
use App\Http\Requests\SendBulkNotificationRequest;
use App\Http\Requests\SendNotificationRequest;
use App\Models\Notification;
use App\Services\NotificationService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
  #[Test]
  public function it_sends_a_single_notification_successfully()
  {
    // 1. Arrange
    $serviceMock = Mockery::mock(NotificationService::class);

    // محاكاة بيانات الخدمة المصادق عليها كمصفوفة
    $authenticatedService = ['name' => 'order-service', 'id' => 1];

    // محاكاة كائن Notification مع معالجة قراءة الخصائص تلقائياً
    $dummyNotification = Mockery::mock(Notification::class);
    $dummyNotification->shouldReceive('getAttribute')
      ->andReturnUsing(function ($key) {
        return match ($key) {
          'id'      => 101,
          'channel' => (object) ['value' => 'in_app'],
          'status'  => (object) ['value' => 'queued'],
          default   => null,
        };
      });

    $payload = [
      'user_id' => 'user-123',
      'channel' => 'in_app',
      'title'   => 'Test Title',
      'body'    => 'Test Body Content',
    ];

    // توقع استدعاء دالة create مع استلام المصفوفة
    $serviceMock->shouldReceive('create')
      ->once()
      ->with($payload, $authenticatedService)
      ->andReturn($dummyNotification);

    // محاكاة الطلب SendNotificationRequest
    $requestMock = Mockery::mock(SendNotificationRequest::class);
    $requestMock->shouldReceive('get')
      ->with('authenticated_service')
      ->andReturn($authenticatedService);

    $requestMock->shouldReceive('validated')
      ->andReturn($payload);

    $controller = new NotificationController($serviceMock);

    // 2. Act
    $response = $controller->send($requestMock);
    $data = json_decode($response->getContent(), true);

    // 3. Assert
    $this->assertEquals(202, $response->getStatusCode());
    $this->assertTrue($data['success']);
    $this->assertEquals('Notification queued successfully.', $data['message']);
    $this->assertEquals(101, $data['data']['notification_id']);
    $this->assertEquals('in_app', $data['data']['channel']);
    $this->assertEquals('queued', $data['data']['status']);
  }

  #[Test]
  public function it_sends_bulk_notifications_successfully()
  {
    // 1. Arrange
    $serviceMock = Mockery::mock(NotificationService::class);

    // محاكاة بيانات الخدمة المصادق عليها كمصفوفة
    $authenticatedService = ['name' => 'order-service', 'id' => 1];

    // محاكاة القائمة المرجعة من createBulk
    $dummyNotifications = [
      ['id' => 201, 'user_id' => 'user-1'],
      ['id' => 202, 'user_id' => 'user-2'],
    ];

    $notificationsPayload = [
      ['user_id' => 'user-1', 'channel' => 'email', 'body' => 'Message 1'],
      ['user_id' => 'user-2', 'channel' => 'email', 'body' => 'Message 2'],
    ];

    // توقع استدعاء دالة createBulk مع استلام المصفوفة
    $serviceMock->shouldReceive('createBulk')
      ->once()
      ->with($notificationsPayload, $authenticatedService)
      ->andReturn($dummyNotifications);

    // محاكاة الطلب SendBulkNotificationRequest
    $requestMock = Mockery::mock(SendBulkNotificationRequest::class);
    $requestMock->shouldReceive('get')
      ->with('authenticated_service')
      ->andReturn($authenticatedService);

    $requestMock->shouldReceive('validated')
      ->andReturn(['notifications' => $notificationsPayload]);

    $controller = new NotificationController($serviceMock);

    // 2. Act
    $response = $controller->sendBulk($requestMock);
    $data = json_decode($response->getContent(), true);

    // 3. Assert
    $this->assertEquals(202, $response->getStatusCode());
    $this->assertTrue($data['success']);
    $this->assertEquals('2 notifications queued.', $data['message']);
    $this->assertEquals([201, 202], $data['data']['notification_ids']);
    $this->assertEquals(2, $data['data']['queued_count']);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }
}
