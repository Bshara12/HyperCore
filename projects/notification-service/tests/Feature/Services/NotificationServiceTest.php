<?php

namespace Tests\Unit\Services;

use App\DTOs\NotificationPayloadDTO;
use App\Enums\NotificationStatus;
use App\Exceptions\UserNotFoundException;
use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use App\Services\Auth\AuthApiClient;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
  use RefreshDatabase;

  private AuthApiClient $authClientMock;
  private NotificationService $service;

  protected function setUp(): void
  {
    parent::setUp();

    Queue::fake();
    Log::spy(); // لمنع أخطاء Monolog وللتحقق من الـ Logs

    /** @var AuthApiClient|\Mockery::MockInterface */
    $this->authClientMock = Mockery::mock(AuthApiClient::class);
    $this->service = new NotificationService($this->authClientMock);
  }

  // ════════════════════════════════════════════════════════════
  // 1. اختبارات create() الخاصة بـ HTTP Controllers
  // ════════════════════════════════════════════════════════════

  #[Test]
  public function it_creates_and_queues_notification_from_http_request_successfully(): void
  {
    $this->authClientMock
      ->shouldReceive('getUserById')
      ->with('user-123')
      ->once()
      ->andReturn([
        'id' => 'user-123',
        'email' => 'user@example.com',
      ]);

    $payload = [
      'user_id' => 'user-123',
      'channel' => 'email',
      'title'   => 'Order Placed',
      'body'    => 'Your order #1001 was successful.',
      'data'    => ['order_id' => 1001],
    ];

    $serviceData = ['name' => 'e-commerce-service'];

    $notification = $this->service->create($payload, $serviceData);

    $this->assertInstanceOf(Notification::class, $notification);
    $this->assertDatabaseHas('notifications', [
      'id'             => $notification->id,
      'user_id'        => 'user-123',
      'user_email'     => 'user@example.com',
      'channel'        => 'email',
      'source_service' => 'e-commerce-service',
      'status'         => NotificationStatus::PENDING->value,
    ]);

    Queue::assertPushedOn('notifications', ProcessNotificationJob::class);

    Log::shouldHaveReceived('info')
      ->once()
      ->with('[NotificationService] Notification queued', Mockery::type('array'));
  }

  #[Test]
  public function it_throws_user_not_found_exception_when_user_does_not_exist(): void
  {
    $this->authClientMock
      ->shouldReceive('getUserById')
      ->with('non-existing-user')
      ->once()
      ->andReturn([]);

    $this->expectException(UserNotFoundException::class);

    $this->service->create([
      'user_id' => 'non-existing-user',
      'channel' => 'in_app',
      'title'   => 'Test',
      'body'    => 'Test Body',
    ], ['name' => 'booking-service']);

    Queue::assertNothingPushed();
  }

  // ════════════════════════════════════════════════════════════
  // 2. اختبارات createFromConsumer() الخاصة بـ RabbitMQ
  // ════════════════════════════════════════════════════════════

  #[Test]
  public function it_creates_notification_from_consumer_verifying_user(): void
  {
    $this->authClientMock
      ->shouldReceive('getUserById')
      ->with('user-456')
      ->once()
      ->andReturn([
        'id' => 'user-456',
        'email' => 'auth-email@example.com',
      ]);

    $dto = new NotificationPayloadDTO(
      userId: 'user-456',
      userEmail: null, // سيتم جلب البريد من Auth Service
      channel: 'real_time',
      title: 'New Message',
      body: 'You have a new message.',
      data: []
    );

    $notification = $this->service->createFromConsumer($dto, ['name' => 'chat-service'], skipUserVerification: false);

    $this->assertEquals('auth-email@example.com', $notification->user_email);
    Queue::assertPushedOn('notifications', ProcessNotificationJob::class);
  }

  #[Test]
  public function it_creates_notification_from_consumer_skipping_user_verification(): void
  {
    // AuthApiClient لا يجب أن يُستدعى هنا مطلقاً
    $this->authClientMock->shouldNotReceive('getUserById');

    $dto = new NotificationPayloadDTO(
      userId: 'user-789',
      userEmail: 'direct@example.com',
      channel: 'email',
      title: 'Welcome!',
      body: 'Account created successfully.',
      data: []
    );

    $notification = $this->service->createFromConsumer($dto, ['name' => 'auth-service'], skipUserVerification: true);

    $this->assertEquals('direct@example.com', $notification->user_email);
    Queue::assertPushedOn('notifications', ProcessNotificationJob::class);
  }

  // ════════════════════════════════════════════════════════════
  // 3. اختبار createBulk()
  // ════════════════════════════════════════════════════════════

  #[Test]
  public function it_creates_bulk_notifications_successfully(): void
  {
    $this->authClientMock
      ->shouldReceive('getUserById')
      ->twice()
      ->andReturn(['id' => 'user-1', 'email' => 'user1@example.com']);

    $notificationsData = [
      ['user_id' => 'user-1', 'channel' => 'in_app', 'title' => 'T1', 'body' => 'B1'],
      ['user_id' => 'user-1', 'channel' => 'email', 'title' => 'T2', 'body' => 'B2'],
    ];

    $results = $this->service->createBulk($notificationsData, ['name' => 'bulk-service']);

    $this->assertCount(2, $results);
    Queue::assertPushed(ProcessNotificationJob::class, 2);
  }

  #[Test]
  public function it_throws_user_not_found_exception_in_consumer_when_user_does_not_exist(): void
  {
    // 1. إعداد الـ Mock ليرجع مصفوفة فارغة عند البحث عن المستخدم
    $this->authClientMock
      ->shouldReceive('getUserById')
      ->with('non-existing-user-999')
      ->once()
      ->andReturn([]);

    $dto = new NotificationPayloadDTO(
      userId: 'non-existing-user-999',
      userEmail: 'test@example.com',
      channel: 'email',
      title: 'Order Status',
      body: 'Your order is processing',
      data: []
    );

    // 2. توقع إطلاق الـ Exception المحدد
    $this->expectException(UserNotFoundException::class);

    // 3. التنفيذ مع ترك skipUserVerification على القيمة الافتراضية (false)
    $this->service->createFromConsumer(
      dto: $dto,
      service: ['name' => 'order-service'],
      skipUserVerification: false
    );

    // 4. التأكد من عدم إرسال أي Job للطور
    Queue::assertNothingPushed();
  }
}
