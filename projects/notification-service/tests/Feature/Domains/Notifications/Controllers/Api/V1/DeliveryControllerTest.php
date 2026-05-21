<?php

namespace Tests\Feature\Domains\Notifications\Controllers\Api\V1;

use Tests\TestCase;
use App\Domains\Notifications\Services\NotificationDeliveryTrackingService;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\DeliveryController;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class DeliveryControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    private MockInterface $deliveryServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        // عمل Mock للخدمة التي يعتمد عليها الـ Controller
        $this->deliveryServiceMock = $this->mock(NotificationDeliveryTrackingService::class);
    }

    #[Test]
    public function it_can_returns_a_collection_of_deliveries_for_a_given_notification()
    {
        // 1. تجهيز المعطيات والـ Attributes المتوقع قراءتها داخل الـ NotificationActor
        $notificationId = 'noti-ulid-123';
        $mockProject = ['string' => 'project-abc'];
        $mockUser = ['id' => 'user-100', 'permessions' => []];

        // 2. تجهيز سجلات الـ Deliveries الوهمية كـ Eloquent Collection للـ Resource
        $delivery1 = (new NotificationDelivery())->forceFill(['id' => 'del-1', 'channel' => 'mail', 'status' => 'sent']);
        $delivery2 = (new NotificationDelivery())->forceFill(['id' => 'del-2', 'channel' => 'sms', 'status' => 'pending']);
        $mockCollection = new Collection([$delivery1, $delivery2]);

        // 3. توقع استدعاء الـ Service وإرجاع المجموعة الوهمية
        $this->deliveryServiceMock
            ->shouldReceive('listForNotification')
            ->once()
            ->andReturn($mockCollection);

        // 4. بناء الـ Request وحقن الـ Attributes يدوياً لتغذية الـ DTO تلقائياً
        $request = Request::create("/api/v1/notifications/{$notificationId}/deliveries", 'GET');
        $request->attributes->set('project', $mockProject);
        $request->attributes->set('auth_user', $mockUser);

        // إخبار الحاوية باستخدام هذا الـ Request
        $this->app->instance('request', $request);

        // 5. استدعاء التابع مباشرة من الـ Controller
        $controller = new DeliveryController($this->deliveryServiceMock);
        $response = $controller->indexByNotification($request, $notificationId);

        // 6. تحويل الـ JsonResponse إلى TestResponse للتحقق من النتيجة بنجاح
        $testResponse = $this->createTestResponse($response, $request);

        $testResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'channel', 'status']
                ]
            ])
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_can_returns_a_specific_delivery_resource_by_id()
    {
        // 1. تجهيز المعطيات والـ Attributes
        $deliveryId = 'del-ulid-999';
        $mockProject = ['string' => 'project-abc'];
        $mockUser = ['id' => 'user-100', 'permessions' => []];

        // 2. تجهيز سجل الـ Delivery الوهمي الراجع
        $delivery = (new NotificationDelivery())->forceFill([
            'id' => $deliveryId,
            'channel' => 'database',
            'status' => 'delivered'
        ]);

        // 3. توقع استدعاء الـ Service وإرجاع السجل
        $this->deliveryServiceMock
            ->shouldReceive('findDelivery')
            ->once()
            ->andReturn($delivery);

        // 4. بناء الـ Request وحقن الـ Attributes
        $request = Request::create("/api/v1/deliveries/{$deliveryId}", 'GET');
        $request->attributes->set('project', $mockProject);
        $request->attributes->set('auth_user', $mockUser);

        $this->app->instance('request', $request);

        // 5. استدعاء التابع مباشرة من الـ Controller
        $controller = new DeliveryController($this->deliveryServiceMock);
        $response = $controller->show($request, $deliveryId);

        // 6. التحقق من النتيجة النهائية
        $testResponse = $this->createTestResponse($response, $request);

        $testResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $deliveryId,
                    'channel' => 'database',
                    'status' => 'delivered'
                ]
            ]);
    }
}