<?php

namespace Tests\Feature\Domains\Notifications\Controllers\Api\V1;

use Tests\TestCase;
use App\Domains\Notifications\Services\NotificationSubscriptionService;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\InternalSubscriptionController;
use App\Http\Requests\Domains\Notifications\Requests\SyncNotificationSubscriptionsRequest;
use App\Models\Domains\Notifications\Models\NotificationSubscription;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Validation\Validator;

class InternalSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase, WithoutMiddleware;

    private MockInterface $subscriptionServiceMock;
    private array $mockUserActor;
    private array $mockProjectAttribute;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->subscriptionServiceMock = $this->mock(NotificationSubscriptionService::class);

        $this->mockProjectAttribute = ['string' => 'project-omega'];
        $this->mockUserActor = [
            'id' => 'user-abc-123',
            'permessions' => ['manage_subscriptions']
        ];
    }

    #[Test]
    public function it_can_sync_project_notification_subscriptions_successfully()
    {
        $payload = [
            'subscriptions' => [
                [
                    'subscriber_type' => 'user',
                    'subscriber_id' => 'sub-777',
                    'topic_key' => 'billing.invoice_paid',
                    'channel_mask' => ['email', 'database'],
                    'filters' => ['amount_gt' => 100],
                    'active' => true,
                ]
            ]
        ];

        $subscriptionModelClass = class_exists(NotificationSubscription::class) 
            ? NotificationSubscription::class 
            : \Illuminate\Database\Eloquent\Model::class;

        $mockSubscription = (new $subscriptionModelClass())->forceFill([
            'id' => 'sub-123',
            'subscriber_type' => 'user',
            'subscriber_id' => 'sub-777',
            'topic_key' => 'billing.invoice_paid',
            'channel_mask' => ['email', 'database'],
            'filters' => ['amount_gt' => 100],
            'active' => true,
        ]);

        // 🎯 الحل: تحويل المصفوفة إلى Eloquent Collection حصراً لمنع الـ TypeError
        $mockSubscriptionsCollection = new \Illuminate\Database\Eloquent\Collection([$mockSubscription]);

        $this->subscriptionServiceMock
            ->shouldReceive('syncForProject')
            ->once()
            ->andReturn($mockSubscriptionsCollection);

        $request = SyncNotificationSubscriptionsRequest::create('/api/v1/subscriptions/sync', 'POST', $payload);
        $request->attributes->set('project', $this->mockProjectAttribute);
        $request->attributes->set('auth_user', $this->mockUserActor);

        $validatorMock = \Mockery::mock(Validator::class);
        $validatorMock->shouldReceive('validated')
            ->once()
            ->andReturn($payload);
            
        $request->setValidator($validatorMock);

        $this->app->instance('request', $request);

        $controller = new InternalSubscriptionController($this->subscriptionServiceMock);
        $response = $controller->sync($request);

        $testResponse = $this->createTestResponse($response, $request);

        $testResponse->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'subscriber_type',
                        'subscriber_id',
                        'topic_key',
                    ]
                ]
            ])
            ->assertJsonFragment([
                'subscriber_id' => 'sub-777',
                'topic_key' => 'billing.invoice_paid'
            ]);
    }
}