<?php

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\ResolveProject;
use App\Services\CMS\CMSApiClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class ResolveProjectTest extends TestCase
{
  private MockInterface $cmsApiClientMock;
  private ResolveProject $middleware;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. تزييف الـ CMSApiClient
    $this->cmsApiClientMock = $this->mock(CMSApiClient::class);

    // 2. بناء الـ Middleware وحقن الـ Mock
    $this->middleware = new ResolveProject($this->cmsApiClientMock);
  }

  #[Test]
  public function it_returns_bad_request_if_x_project_id_header_is_missing()
  {
    // إنشاء Request بدون الهيدر المطلوب
    $request = Request::create('/api/any-route', 'GET');

    $next = function ($req) {
      $this->fail('Should not pass to the next middleware.');
    };

    $response = $this->middleware->handle($request, $next);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $this->assertEquals(400, $response->getStatusCode());
    $this->assertJsonStringEqualsJsonString(
      json_encode(['message' => 'X-Project-Id header is required']),
      $response->getContent()
    );
  }

  #[Test]
  public function it_returns_unprocessable_entity_if_resolver_throws_exception()
  {
    // إنشاء Request يحتوي على الهيدر
    $request = Request::create('/api/any-route', 'GET');
    $request->headers->set('X-Project-Id', 'project-omega-123');

    // ضبط الـ Mock ليرمي Throwable عند محاولة الـ Resolve
    $this->cmsApiClientMock
      ->shouldReceive('resolveProject')
      ->once()
      ->andThrow(new \Exception('Connection failed or project not found'));

    $next = function ($req) {
      $this->fail('Should not pass to the next middleware.');
    };

    $response = $this->middleware->handle($request, $next);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $this->assertEquals(422, $response->getStatusCode());
    $this->assertJsonStringEqualsJsonString(
      json_encode(['message' => 'Failed to resolve project']),
      $response->getContent()
    );
  }

  #[Test]
  public function it_resolves_project_merges_data_and_sets_attributes_successfully()
  {
    $request = Request::create('/api/any-route', 'GET');
    $request->headers->set('X-Project-Id', 'project-omega-123');

    $mockProjectData = [
      'id' => 'project-omega-123',
      'name' => 'Omega Portal',
      'status' => 'active'
    ];

    // ضبط الـ Mock ليعيد بيانات المشروع بنجاح
    $this->cmsApiClientMock
      ->shouldReceive('resolveProject')
      ->once()
      ->andReturn($mockProjectData);

    $nextCalled = false;
    $next = function ($req) use (&$nextCalled, $mockProjectData) {
      $nextCalled = true;

      // 1. التحقق من دمج البيانات عبر الـ merge() في الـ Request body/input
      $this->assertEquals('project-omega-123', $req->input('project_id'));
      $this->assertEquals($mockProjectData, $req->input('project'));

      // 2. التحقق من حقن البيانات داخل الـ Attributes
      $injectedAttribute = $req->attributes->get('project');
      $this->assertEquals($mockProjectData, $injectedAttribute);

      return response('Passed');
    };

    $response = $this->middleware->handle($request, $next);

    $this->assertTrue($nextCalled);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Passed', $response->getContent());
  }
}
