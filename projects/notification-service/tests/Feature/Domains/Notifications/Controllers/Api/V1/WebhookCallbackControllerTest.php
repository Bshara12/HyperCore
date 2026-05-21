<?php

namespace Tests\Feature\Domains\Notifications\Controllers\Api\V1;

use Tests\TestCase;
use App\Domains\Notifications\Services\NotificationWebhookCallbackService;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\WebhookCallbackController;
use App\Http\Requests\Domains\Notifications\Requests\WebhookDeliveryCallbackRequest;
use App\Models\Domains\Notifications\Models\NotificationDelivery; // تأكد من الـ Namespace الفعلي للموديل لديك
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Validation\Validator;

class WebhookCallbackControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    private MockInterface $callbackServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // تزييف السيرفس المستهدفة
        $this->callbackServiceMock = $this->mock(NotificationWebhookCallbackService::class);
    }

    private function createMockDelivery(): NotificationDelivery
    {
        // تأمين الموديل حتى لو لم يكن الـ Class متوفراً بعد في بيئة الاختبار الحالية
        $deliveryClass = class_exists(NotificationDelivery::class)
            ? NotificationDelivery::class
            : \Illuminate\Database\Eloquent\Model::class;

        return (new $deliveryClass())->forceFill([
            'id' => 'delivery-123',
            'notification_id' => 'noti-abc',
            'channel' => 'webhook',
            'status' => 'delivered',
            'response_payload' => ['status' => 'success'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function it_can_handle_webhook_delivery_callback_successfully()
    {
        $payload = [
            'event' => 'notification.delivered',
            'delivery_id' => 'delivery-123',
            'timestamp' => time(),
        ];

        $mockDelivery = $this->createMockDelivery();

        // 🎯 ضبط الـ Mock ليتوقع استلام كائن الـ Request ومصفوفة البيانات المفلترة
        $this->callbackServiceMock
            ->shouldReceive('handle')
            ->once()
            ->with(\Mockery::any(), $payload)
            ->andReturn($mockDelivery);

        // إنشاء الـ FormRequest وتمرير الـ Payload
        $request = WebhookDeliveryCallbackRequest::create('/api/v1/webhooks/callback', 'POST', $payload);

        // تزييف الـ Validator لمنع الـ FormRequest من تفعيل الفحص الحقيقي وإرجاع الـ payload مباشرة
        $validatorMock = \Mockery::mock(Validator::class);
        $validatorMock->shouldReceive('validated')->once()->andReturn($payload);
        $request->setValidator($validatorMock);

        // حقن الـ Request داخل الـ Container الخاص بلارافيل
        $this->app->instance('request', $request);

        // استدعاء الـ Controller وتنفيذ الدالة
        $controller = new WebhookCallbackController($this->callbackServiceMock);
        $response = $controller->store($request);

        // التحقق من صحة الرد وبنية الـ JSON
        $testResponse = $this->createTestResponse($response, $request);
        $testResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => []
            ]);
    }
}