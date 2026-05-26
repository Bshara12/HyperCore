<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\ResolveProject;
use App\Services\CMS\CMSApiClient;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use PHPUnit\Framework\Attributes\Test;

class ResolveProjectTest extends TestCase
{
  private MockInterface $cmsClientMock;
  private ResolveProject $middleware;

  protected function setUp(): void
  {
    parent::setUp();
    $this->cmsClientMock = $this->mock(CMSApiClient::class);
    $this->middleware = new ResolveProject($this->cmsClientMock);
  }

  #[Test]
  public function it_aborts_with_400_if_project_header_is_missing()
  {
    $request = Request::create('/test', 'GET');
    // لا نقوم بضبط الـ Header 'X-Project-Id'

    $this->expectException(HttpException::class);
    $this->expectExceptionMessage('X-Project-Id header is required');

    $this->middleware->handle($request, function () {
      return response('OK');
    });
  }

  #[Test]
  public function it_resolves_project_and_merges_data_into_request()
  {
    $projectData = [
      'id' => 7,
      'name' => 'My Awesome Project',
      'enabled_modules' => ['ecommerce']
    ];

    $request = Request::create('/test', 'GET');
    $request->headers->set('X-Project-Id', '7');

    // محاكاة عمل الـ Resolver
    $this->cmsClientMock->shouldReceive('resolveProject')
      ->once()
      ->andReturn($projectData);

    $nextCalled = false;
    $response = $this->middleware->handle($request, function ($req) use (&$nextCalled, $projectData) {
      $nextCalled = true;

      // التحقق من حقن البيانات المذكورة في تعليق الـ "مهم"
      $this->assertEquals($projectData['id'], $req->get('project_id'));
      $this->assertEquals($projectData, $req->get('project'));

      return response('OK');
    });

    $this->assertTrue($nextCalled);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_fails_if_resolver_throws_exception()
  {
    $request = Request::create('/test', 'GET');
    $request->headers->set('X-Project-Id', '99');

    $this->cmsClientMock->shouldReceive('resolveProject')
      ->andThrow(new \Exception('Project not found'));

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Project not found');

    $this->middleware->handle($request, function () {
      return response('OK');
    });
  }
}
