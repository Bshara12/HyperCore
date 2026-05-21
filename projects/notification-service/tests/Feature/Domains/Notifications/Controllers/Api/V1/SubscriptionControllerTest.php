<?php

namespace Tests\Feature\Domains\Notifications\Controllers\Api\V1;

use Tests\TestCase;
use App\Domains\Notifications\Services\NotificationSubscriptionService;
use App\Http\Controllers\Domains\Notifications\Controllers\Api\V1\SubscriptionController;
use App\Http\Requests\Domains\Notifications\Requests\StoreNotificationSubscriptionRequest;
use App\Http\Requests\Domains\Notifications\Requests\UpdateNotificationSubscriptionRequest;
use App\Models\Domains\Notifications\Models\NotificationSubscription;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Request;

class SubscriptionControllerTest extends TestCase
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

  private function createMockSubscription(string $id = 'sub-123'): NotificationSubscription
  {
    $subscriptionClass = class_exists(NotificationSubscription::class)
      ? NotificationSubscription::class
      : \Illuminate\Database\Eloquent\Model::class;

    return (new $subscriptionClass())->forceFill([
      'id' => $id,
      'subscriber_type' => 'user',
      'subscriber_id' => 'user-abc-123',
      'topic_key' => 'billing.invoice_paid',
      'channel_mask' => ['email', 'database'],
      'filters' => [],
      'active' => true,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  #[Test]
  public function it_can_list_subscriptions_for_actor()
  {
    $mockSubscription = $this->createMockSubscription();
    $mockCollection = new \Illuminate\Database\Eloquent\Collection([$mockSubscription]);

    $this->subscriptionServiceMock
      ->shouldReceive('listForActor')
      ->once()
      ->with(\Mockery::any())
      ->andReturn($mockCollection);

    $request = Request::create('/api/v1/subscriptions', 'GET');
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $this->app->instance('request', $request);

    $controller = new SubscriptionController($this->subscriptionServiceMock);
    $response = $controller->index($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJsonPath('data.0.id', 'sub-123');
  }

  #[Test]
  public function it_can_store_subscription_for_actor_successfully()
  {
    $payload = [
      'subscriber_type' => 'user',
      'subscriber_id' => 'user-abc-123',
      'topic_key' => 'billing.invoice_paid',
      'channel_mask' => ['email'],
    ];

    $mockSubscription = $this->createMockSubscription();

    $this->subscriptionServiceMock
      ->shouldReceive('createForActor')
      ->once()
      ->with(\Mockery::any(), $payload)
      ->andReturn($mockSubscription);

    $request = StoreNotificationSubscriptionRequest::create('/api/v1/subscriptions', 'POST', $payload);
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $validatorMock = \Mockery::mock(Validator::class);
    $validatorMock->shouldReceive('validated')->once()->andReturn($payload);
    $request->setValidator($validatorMock);

    $this->app->instance('request', $request);

    $controller = new SubscriptionController($this->subscriptionServiceMock);
    $response = $controller->store($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(201)
      ->assertJsonPath('data.id', 'sub-123');
  }

  #[Test]
  public function it_can_show_single_subscription_for_actor()
  {
    $mockSubscription = $this->createMockSubscription('sub-789');

    $this->subscriptionServiceMock
      ->shouldReceive('findForActor')
      ->once()
      ->with(\Mockery::any(), 'sub-789')
      ->andReturn($mockSubscription);

    $request = Request::create('/api/v1/subscriptions/sub-789', 'GET');
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $this->app->instance('request', $request);

    $controller = new SubscriptionController($this->subscriptionServiceMock);
    $response = $controller->show($request, 'sub-789');

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJsonPath('data.id', 'sub-789');
  }

  #[Test]
  public function it_can_update_subscription_for_actor_successfully()
  {
    $payload = [
      'channel_mask' => ['email', 'broadcast'],
      'active' => false,
    ];

    $mockSubscription = $this->createMockSubscription('sub-789');

    // التوقع الأول: البحث عن الاشتراك
    $this->subscriptionServiceMock
      ->shouldReceive('findForActor')
      ->once()
      ->with(\Mockery::any(), 'sub-789')
      ->andReturn($mockSubscription);

    // التوقع الثاني: تحديث الاشتراك وإرجاع الكائن المعدل
    $updatedSubscription = $this->createMockSubscription('sub-789');
    $updatedSubscription->active = false;

    $this->subscriptionServiceMock
      ->shouldReceive('updateForActor')
      ->once()
      ->with(\Mockery::any(), $mockSubscription, $payload)
      ->andReturn($updatedSubscription);

    $request = UpdateNotificationSubscriptionRequest::create('/api/v1/subscriptions/sub-789', 'PUT', $payload);
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $validatorMock = \Mockery::mock(Validator::class);
    $validatorMock->shouldReceive('validated')->once()->andReturn($payload);
    $request->setValidator($validatorMock);

    $this->app->instance('request', $request);

    $controller = new SubscriptionController($this->subscriptionServiceMock);
    $response = $controller->update($request, 'sub-789');

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200);
  }

  #[Test]
  public function it_can_destroy_subscription_for_actor_successfully()
  {
    $mockSubscription = $this->createMockSubscription('sub-789');

    // التوقع الأول: البحث عن الاشتراك قبل الحذف
    $this->subscriptionServiceMock
      ->shouldReceive('findForActor')
      ->once()
      ->with(\Mockery::any(), 'sub-789')
      ->andReturn($mockSubscription);

    // التوقع الثاني: استدعاء دالة الحذف
    $this->subscriptionServiceMock
      ->shouldReceive('deleteForActor')
      ->once()
      ->with(\Mockery::any(), $mockSubscription)
      ->andReturn(true);

    $request = Request::create('/api/v1/subscriptions/sub-789', 'DELETE');
    $request->attributes->set('project', $this->mockProjectAttribute);
    $request->attributes->set('auth_user', $this->mockUserActor);

    $this->app->instance('request', $request);

    $controller = new SubscriptionController($this->subscriptionServiceMock);
    $response = $controller->destroy($request, 'sub-789');

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJson(['message' => 'Subscription deleted successfully.']);
  }
}
  