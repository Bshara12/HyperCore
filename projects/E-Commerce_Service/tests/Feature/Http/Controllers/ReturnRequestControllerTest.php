<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\ReturnRequestService;
use App\Http\Controllers\ReturnRequestController;
use App\Domains\E_Commerce\Requests\CreateReturnRequestRequest;
use App\Domains\E_Commerce\Requests\UpdateReturnRequestRequest;
use Mockery\MockInterface;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class ReturnRequestControllerTest extends TestCase
{
  use WithoutMiddleware;

  private MockInterface $serviceMock;
  private array $mockOwner = [
    'id' => 1,
    'roles' => [['name' => 'owner']]
  ];
  private array $mockUser = [
    'id' => 2,
    'roles' => [['name' => 'customer']]
  ];

  protected function setUp(): void
  {
    parent::setUp();
    $this->serviceMock = $this->mock(ReturnRequestService::class);
  }

  #[Test]
  public function it_can_store_return_request_successfully()
  {
    $payload = [
      'order_id' => 10,
      'order_item_id' => 5,
      'description' => 'Product is damaged',
      'quantity' => 1,
      'project_id' => 100
    ];

    $this->serviceMock->shouldReceive('create')->once()->andReturn(['id' => 1]);

    $request = CreateReturnRequestRequest::create('/api/returns', 'POST', $payload);
    $request->attributes->set('auth_user', $this->mockUser);
    $this->app->instance(CreateReturnRequestRequest::class, $request);

    $controller = new ReturnRequestController($this->serviceMock);
    $response = $controller->store($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)->assertJson(['message' => 'Return request created']);
  }

  #[Test]
  public function it_can_update_return_request_status()
  {
    $id = 1;
    $payload = ['status' => 'approved'];

    $this->serviceMock->shouldReceive('update')->once()->andReturn(['id' => $id, 'status' => 'approved']);

    $request = UpdateReturnRequestRequest::create("/api/returns/{$id}", 'PUT', $payload);
    $this->app->instance(UpdateReturnRequestRequest::class, $request);

    $controller = new ReturnRequestController($this->serviceMock);
    $response = $controller->update($request, $id);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)->assertJson(['message' => 'Return request updated']);
  }

  #[Test]
  public function it_can_index_return_requests_for_owner()
  {
    $this->serviceMock->shouldReceive('getAll')->once()->andReturn([]);

    $request = Request::create('/api/returns', 'GET', ['project_id' => 100]);
    $request->attributes->set('auth_user', $this->mockOwner);
    $this->app->instance('request', $request);

    $controller = new ReturnRequestController($this->serviceMock);
    $response = $controller->index($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)->assertJson(['message' => 'Return requests fetched successfully']);
  }

  #[Test]
  public function it_throws_exception_if_non_owner_tries_to_index()
  {
    $request = Request::create('/api/returns', 'GET', ['project_id' => 100]);
    $request->attributes->set('auth_user', $this->mockUser); // مستخدم عادي

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Unauthorized');

    $controller = new ReturnRequestController($this->serviceMock);
    $controller->index($request);
  }
}
