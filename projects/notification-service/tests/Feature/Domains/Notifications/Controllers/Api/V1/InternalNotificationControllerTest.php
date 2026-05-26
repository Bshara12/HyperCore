<?php

namespace Tests\Feature\Domains\Notifications\Controllers\Api\V1;

use Tests\TestCase;
use App\Domains\Notifications\Services\NotificationWriteService;
use App\Domains\Notifications\Services\NotificationBatchService;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\InternalNotificationController;
use App\Http\Requests\Domains\Notifications\Requests\CreateNotificationRequest;
use App\Http\Requests\Domains\Notifications\Requests\CreateBulkNotificationRequest;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationBatch;
use App\Domains\Notifications\Enums\NotificationStatus; // 🎯 استيراد الـ Enum الفعلي الخاص بك
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Validation\Validator;

class InternalNotificationControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    private MockInterface $writeServiceMock;
    private MockInterface $batchServiceMock;
    private array $mockUserActor;
    private array $mockProjectAttribute;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->writeServiceMock = $this->mock(NotificationWriteService::class);
        $this->batchServiceMock = $this->mock(NotificationBatchService::class);

        $this->mockProjectAttribute = ['string' => 'project-omega'];
        $this->mockUserActor = [
            'id' => 'user-abc-123',
            'permessions' => ['send_notifications']
        ];
    }

    #[Test]
    public function it_can_store_system_notification_successfully()
    {
        $payload = [
            'project_id'   => 'project-omega',
            'recipient'    => ['type' => 'user', 'id' => 'recipient-777'],
            'source'       => ['service' => 'order-service', 'type' => 'system', 'id' => 'order-99'],
            'title'        => 'تحديث حالة الطلب',
            'body'         => 'تم شحن طلبك بنجاح وهو الآن في الطريق إليك.',
            'channel'      => ['database', 'broadcast'],
            'priority'     => 1,
        ];

        // 🎯 حقن الـ Enum الحقيقي المتوافق مع الـ Cast الخاص بالـ Model مباشرة لتخطي الـ Resource
        $mockNotification = (new Notification())->forceFill([
            'id' => 'noti-123',
            'project_id' => 'project-omega',
            'recipient_id' => 'recipient-777',
            'title' => 'تحديث حالة الطلب',
            'body' => 'تم شحن طلبك بنجاح وهو الآن في الطريق إليك.',
            'status' => NotificationStatus::Pending, // استخدام الكيس الفعلي المباشر هنا
            'priority' => 1,
            'topic_key' => null,
            'data' => [],
        ]);

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

        $controller = new InternalNotificationController($this->writeServiceMock, $this->batchServiceMock);
        $response = $controller->storeSystem($request);

        $testResponse = $this->createTestResponse($response, $request);

        $testResponse->assertStatus(201)
            ->assertJson([
                'data' => [
                    'id' => 'noti-123',
                    'title' => 'تحديث حالة الطلب'
                ]
            ]);
    }

    #[Test]
    public function it_can_store_bulk_notifications_successfully()
    {
        $payload = [
            'project_id' => 'project-omega',
            'notifications' => [
                [
                    'recipient' => ['type' => 'user', 'id' => 'user-1'],
                    'title' => 'رسالة جماعية 1',
                    'body' => 'محتوى الرسالة الأولى',
                    'channel' => ['database']
                ]
            ]
        ];

        $mockBatch = (new NotificationBatch())->forceFill([
            'id' => 'batch-xyz-987',
            'project_id' => 'project-omega',
            'total_count' => 1,
            'status' => 'pending'
        ]);

        $this->batchServiceMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($mockBatch);

        $request = CreateBulkNotificationRequest::create('/api/v1/notifications/bulk', 'POST', $payload);
        $request->attributes->set('project', $this->mockProjectAttribute);
        $request->attributes->set('auth_user', $this->mockUserActor);

        $validatorMock = \Mockery::mock(Validator::class);
        $validatorMock->shouldReceive('validated')->once()->andReturn($payload);
        $request->setValidator($validatorMock);

        $this->app->instance('request', $request);

        $controller = new InternalNotificationController($this->writeServiceMock, $this->batchServiceMock);
        $response = $controller->storeBulk($request);

        $testResponse = $this->createTestResponse($response, $request);

        $testResponse->assertStatus(202)
            ->assertJson([
                'data' => [
                    'id' => 'batch-xyz-987'
                ]
            ]);
    }
}