<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\ContentAccessController;
use App\Domains\Subscription\Services\ContentAccessManagementService;
use App\Domains\Subscription\Requests\ContentAccess\CreateContentAccessRequest;
use App\Domains\Subscription\Requests\ContentAccess\UpdateContentAccessMetadataRequest;
use App\Models\ContentAccessMetadata;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class ContentAccessControllerTest extends TestCase
{
  private MockInterface $serviceMock;
  private ContentAccessController $controller;

  protected function setUp(): void
  {
    parent::setUp();
    $this->serviceMock = $this->mock(ContentAccessManagementService::class);
    $this->controller = new ContentAccessController($this->serviceMock);
  }

  #[Test]
  public function it_can_store_content_access()
  {
    // 1. إنشاء Model حقيقي لتجنب الـ TypeError
    $metadata = new ContentAccessMetadata();
    $metadata->id = 1;

    // 2. إعداد الـ Mock للـ Request
    $request = $this->mock(CreateContentAccessRequest::class);
    $request->project_id = 1;
    $request->content_id = 10;
    $request->shouldReceive('boolean')->with('requires_subscription')->andReturn(true);
    $request->shouldReceive('input')->with('features', [])->andReturn(['premium']);
    $request->shouldReceive('input')->with('metadata')->andReturn([]);

    $this->app->instance(CreateContentAccessRequest::class, $request);

    // 3. إرجاع الموديل بدلاً من المصفوفة
    $this->serviceMock->shouldReceive('create')->once()->andReturn($metadata);

    $response = $this->controller->store($request);

    $this->assertEquals(201, $response->getStatusCode());
  }

  #[Test]
  public function it_can_update_content_access()
  {
    $metadata = new ContentAccessMetadata();

    // إعداد بيانات وهمية للـ Request
    $requestData = [
      'project_id' => 1,
      'content_id' => 10,
    ];

    $request = $this->mock(UpdateContentAccessMetadataRequest::class);

    // حل مشكلة all() التي يستخدمها الـ DTO عند الوصول للـ property
    $request->shouldReceive('all')->andReturn($requestData);
    // حل مشكلة الـ Property access (magic methods)
    $request->project_id = 1;
    $request->content_id = 10;

    $request->shouldReceive('input')->with('content_id')->andReturn(10);
    $request->shouldReceive('input')->with('features', [])->andReturn(['basic']);
    $request->shouldReceive('input')->with('metadata')->andReturn([]);
    $request->shouldReceive('boolean')->andReturn(true);

    $this->app->instance(UpdateContentAccessMetadataRequest::class, $request);

    $this->serviceMock->shouldReceive('update')->once()->andReturn($metadata);

    $response = $this->controller->update($request, $metadata);

    $this->assertEquals(200, $response->getStatusCode());
  }

#[Test]
    public function it_can_destroy_content_access_and_return_disabled_message()
    {
        // نستخدم makePartial للسماح بـ Mocking لتابع واحد (wasChanged) مع بقاء الموديل يعمل
        $metadata = \Mockery::mock(ContentAccessMetadata::class)->makePartial();
        
        // الحالة الأولى: تم تغيير الحالة بنجاح
        $metadata->shouldReceive('wasChanged')->with('is_active')->once()->andReturn(true);

        $this->serviceMock->shouldReceive('disable')->once()->andReturn($metadata);

        $response = $this->controller->destroy($metadata);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Content access disabled.', $response->getData()->message);
    }

    #[Test]
    public function it_can_destroy_content_access_and_return_already_disabled_message()
    {
        $metadata = \Mockery::mock(ContentAccessMetadata::class)->makePartial();
        
        // الحالة الثانية: الحالة لم تتغير (كانت false مسبقاً)
        $metadata->shouldReceive('wasChanged')->with('is_active')->once()->andReturn(false);

        $this->serviceMock->shouldReceive('disable')->once()->andReturn($metadata);

        $response = $this->controller->destroy($metadata);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Content access already disabled.', $response->getData()->message);
    }

    #[Test]
    public function it_can_activate_content_access()
    {
        // في التفعيل، لا نحتاج لتغيير الحالة، نستخدم موديل عادي
        $metadata = new ContentAccessMetadata();

        $this->serviceMock->shouldReceive('activate')->once()->andReturn($metadata);

        $response = $this->controller->activate($metadata);

        $this->assertEquals(200, $response->getStatusCode());
        // التأكد من أن البيانات تعود في الـ json
        $this->assertNotNull($response->getData()->data);
    }

  #[Test]
  public function it_can_list_content_access()
  {
    $data = [['id' => 1]];
    $this->serviceMock->shouldReceive('list')->once()->andReturn($data);

    $response = $this->controller->index();
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_show_content_access()
  {
    $metadata = new ContentAccessMetadata();
    $metadata->id = 1;

    $this->serviceMock->shouldReceive('show')->with(1)->once()->andReturn($metadata);

    $response = $this->controller->show(1);

    $this->assertEquals(200, $response->getStatusCode());
  }
}
