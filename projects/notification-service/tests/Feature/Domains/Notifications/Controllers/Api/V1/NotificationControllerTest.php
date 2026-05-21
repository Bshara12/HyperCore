<?php

namespace Tests\Feature\Domains\Notifications\Controllers\Api\V1;

use Tests\TestCase;
use App\Domains\Notifications\Services\NotificationReadService;
use App\Domains\Notifications\Services\NotificationWriteService;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\NotificationController;
use App\Http\Requests\Domains\Notifications\Requests\CreateNotificationRequest;
use App\Http\Requests\Domains\Notifications\Requests\MarkAllAsReadRequest;
use App\Models\Domains\Notifications\Models\Notification;
use App\Domains\Notifications\Enums\NotificationStatus;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationControllerTest extends TestCase
{
  use RefreshDatabase, WithoutMiddleware;

  private MockInterface $readServiceMock;
  private MockInterface $writeServiceMock;
  private array $mockUserActor;
  private array $mockProjectAttribute;

  protected function setUp(): void
  {
    parent::setUp();

    $this->readServiceMock = $this->mock(NotificationReadService::class);
    $this->writeServiceMock = $this->mock(NotificationWriteService::class);

    $this->mockProjectAttribute = ['string' => 'project-omega'];
    $this->mockUserActor = [
      'id' => 'user-abc-123',
      'permessions' => ['manage_notifications']
    ];
  }

  private function createMockNotification(string $id = 'noti-123'): Notification
  {
    return (new Notification())->forceFill([
      'id' => $id,
      'project_id' => 'project-omega',
      'recipient_type' => 'user',
      'recipient_id' => 'recipient-777',
      'title' => 'تحديث حالة الطلب',
      'body' => 'تم شحن طلبك بنجاح.',
      'status' => NotificationStatus::Pending,
      'priority' => 1,
      'topic_key' => 'order.shipping',
      'data' => [],
      'metadata' => [],
      'source_type' => 'system',
      'source_service' => 'order-service',
      'source_id' => 'order-99',
      'read_at' => null,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  #[Test]
  public function it_can_store_notification_successfully()
  {
    $payload = [
      'project_id' => 'project-omega',
      'recipient' => ['type' => 'user', 'id' => 'recipient-777'],
      'source' => ['service' => 'order-service', 'type' => 'system', 'id' => 'order-99'],
      'title' => 'تحديث حالة الطلب',
      'body' => 'تم شحن طلبك بنجاح.',
      'channel' => ['database'],
    ];

    $mockNotification = $this->createMockNotification();

    $this->writeServiceMock
      ->shouldReceive('create')
      ->once()
      ->andReturn($mockNotification);

    $request = CreateNotificationRequest::create('/api/v1/notifications', 'POST', $payload);
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $validatorMock = \Mockery::mock(Validator::class);
    $validatorMock->shouldReceive('validated')->once()->andReturn($payload);
    $request->setValidator($validatorMock);

    $this->app->instance('request', $request);

    $controller = new NotificationController($this->readServiceMock, $this->writeServiceMock);
    $response = $controller->store($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(201)
      ->assertJsonPath('data.id', 'noti-123');
  }

  #[Test]
  public function it_can_store_system_notification_successfully()
  {
    $payload = [
      'project_id' => 'project-omega',
      'recipient' => ['type' => 'user', 'id' => 'recipient-777'],
      'source' => ['service' => 'order-service', 'type' => 'system', 'id' => 'order-99'],
      'title' => 'تحديث حالة الطلب',
      'body' => 'تم شحن طلبك بنجاح.',
      'channel' => ['database'],
    ];

    $mockNotification = $this->createMockNotification();

    $this->writeServiceMock
      ->shouldReceive('create')
      ->once()
      ->andReturn($mockNotification);

    $request = CreateNotificationRequest::create('/api/v1/notifications/system', 'POST', $payload);
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $validatorMock = \Mockery::mock(Validator::class);
    $validatorMock->shouldReceive('validated')->once()->andReturn($payload);
    $request->setValidator($validatorMock);

    $this->app->instance('request', $request);

    $controller = new NotificationController($this->readServiceMock, $this->writeServiceMock);
    $response = $controller->storeSystem($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(201);
  }

  #[Test]
  public function it_can_paginate_notifications_for_actor()
  {
    $mockNotification = $this->createMockNotification();

    $paginator = new LengthAwarePaginator(
      items: collect([$mockNotification]),
      total: 1,
      perPage: 20,
      currentPage: 1
    );

    $this->readServiceMock
      ->shouldReceive('paginateForActor')
      ->once()
      ->with(\Mockery::any(), [
        'status' => 'pending',
        'unread_only' => true,
        'topic_key' => 'order.shipping',
      ], 20)
      ->andReturn($paginator);

    $request = Request::create('/api/v1/notifications', 'GET', [
      'per_page' => 20,
      'status' => 'pending',
      'unread_only' => '1',
      'topic_key' => 'order.shipping'
    ]);
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $this->app->instance('request', $request);

    $controller = new NotificationController($this->readServiceMock, $this->writeServiceMock);
    $response = $controller->index($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJsonStructure(['data', 'meta' => ['current_page', 'total']]);
  }

  #[Test]
  public function it_can_show_single_notification()
  {
    $mockNotification = $this->createMockNotification();

    $this->readServiceMock
      ->shouldReceive('findForActor')
      ->once()
      ->with(\Mockery::any(), 'noti-123')
      ->andReturn($mockNotification);

    $request = Request::create('/api/v1/notifications/noti-123', 'GET');
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $this->app->instance('request', $request);

    $controller = new NotificationController($this->readServiceMock, $this->writeServiceMock);
    $response = $controller->show($request, 'noti-123');

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJsonPath('data.id', 'noti-123');
  }

  #[Test]
  public function it_can_mark_notification_as_read()
  {
    $mockNotification = $this->createMockNotification();
    $mockNotification->read_at = now();

    $this->readServiceMock
      ->shouldReceive('markAsRead')
      ->once()
      ->with(\Mockery::any(), 'noti-123')
      ->andReturn($mockNotification);

    $request = Request::create('/api/v1/notifications/noti-123/read', 'PATCH');
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $this->app->instance('request', $request);

    $controller = new NotificationController($this->readServiceMock, $this->writeServiceMock);
    $response = $controller->markAsRead($request, 'noti-123');

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200);
  }

  #[Test]
  public function it_can_mark_all_notifications_as_read()
  {
    $this->readServiceMock
      ->shouldReceive('markAllAsRead')
      ->once()
      ->with(\Mockery::any())
      ->andReturn(5);

    $request = MarkAllAsReadRequest::create('/api/v1/notifications/read-all', 'POST');
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    // 🎯 التعديل: جعل التوقع مرناً لأن الـ Controller لا يستدعي validated() في هذا الأكشن
    $validatorMock = \Mockery::mock(Validator::class);
    $validatorMock->shouldReceive('validated')->zeroOrMoreTimes()->andReturn([]);
    $request->setValidator($validatorMock);

    $this->app->instance('request', $request);

    $controller = new NotificationController($this->readServiceMock, $this->writeServiceMock);
    $response = $controller->markAllAsRead($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJsonPath('data.updated_count', 5);
  }
  #[Test]
  public function it_can_get_unread_notifications_count()
  {
    $this->readServiceMock
      ->shouldReceive('unreadCount')
      ->once()
      ->with(\Mockery::any())
      ->andReturn(12);

    $request = Request::create('/api/v1/notifications/unread-count', 'GET');
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $this->app->instance('request', $request);

    $controller = new NotificationController($this->readServiceMock, $this->writeServiceMock);
    $response = $controller->unreadCount($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJsonPath('data.count', 12);
  }
}
