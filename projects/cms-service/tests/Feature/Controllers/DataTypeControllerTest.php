<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\DataTypeController;
use App\Domains\CMS\Services\DataTypeService;
use App\Domains\CMS\Read\Services\DataTypeReadService;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Requests\CreateDataTypeRequest;
use App\Domains\CMS\Requests\UpdateDataTypeRequest;
use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Read\DTOs\DataType\ShowDataTypeDTOProperities;
use App\Models\DataType;
use App\Models\Project; // 🌟 استدعاء موديل المشروع الحقيقي لتجنب الـ TypeError
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class DataTypeControllerTest extends TestCase
{
  private DataTypeController $controller;
  private $dataTypeServiceMock;
  private $dataTypeReadServiceMock;
  private $projectRepoMock;

  protected function setUp(): void
  {
    parent::setUp();

    $this->dataTypeServiceMock = Mockery::mock(DataTypeService::class);
    $this->dataTypeReadServiceMock = Mockery::mock(DataTypeReadService::class);
    $this->projectRepoMock = Mockery::mock(ProjectRepositoryInterface::class);

    // 1. تلبية متطلبات حاوية لارافيل للمشروع الحالي
    $this->app->instance(ProjectRepositoryInterface::class, $this->projectRepoMock);
    $this->app->instance('currentProject', (object)[
      'id' => 1,
      'public_id' => 'proj_abc123'
    ]);

    // 2. 🔥 الحل الجذري للـ TypeError والـ BadMethodCallException:
    // ننشئ كائن حقيقي من كلاس الموديل (دون حفظه في القاعدة) لتخطي الـ Type Hint بسلام
    $projectModel = new Project();
    $projectModel->id = 1;

    // جعل التوقع يعمل تلقائياً لكافة الاختبارات عبر byDefault()
    $this->projectRepoMock->shouldReceive('findByKey')
      ->byDefault()
      ->with('proj_abc123')
      ->andReturn($projectModel);

    $this->controller = new DataTypeController(
      $this->dataTypeServiceMock,
      $this->dataTypeReadServiceMock
    );
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  // ==========================================
  // 1. اختبار دالة INDEX
  // ==========================================
  #[Test]
  public function it_can_list_all_data_types()
  {
    $mockTypes = [['id' => 1, 'name' => 'Posts']];

    $this->dataTypeReadServiceMock->shouldReceive('list')->once()->andReturn($mockTypes);

    $response = $this->controller->index();
    $this->assertEquals(200, $response->getStatusCode());
  }

  // ==========================================
  // 2. اختبار دالة STORE
  // ==========================================
  #[Test]
  public function it_can_store_a_data_type_successfully()
  {
    $requestMock = Mockery::mock(CreateDataTypeRequest::class);
    $data = [
      'name' => 'Products',
      'slug' => 'products',
      'description' => 'Product data type',
      'is_active' => true
    ];

    $requestMock->shouldReceive('all')->andReturn($data);
    $requestMock->shouldReceive('input')->andReturnUsing(function ($key, $default = null) use ($data) {
      return $data[$key] ?? $default;
    });
    // 🔥 حل مشكلة استدعاء دالة boolean() داخل الـ DTO
    $requestMock->shouldReceive('boolean')->andReturnUsing(function ($key, $default = false) use ($data) {
      return isset($data[$key]) ? (bool)$data[$key] : $default;
    });

    $this->dataTypeServiceMock->shouldReceive('create')
      ->once()
      ->with(Mockery::type(CreateDataTypeDTO::class))
      ->andReturn(array_merge($data, ['id' => 5]));

    $response = $this->controller->store($requestMock);
    $this->assertEquals(201, $response->getStatusCode());
  }

  // ==========================================
  // 3. اختبارات دالة SHOW
  // ==========================================
  #[Test]
  public function it_can_show_a_data_type_by_slug_if_found()
  {
    $slug = 'articles';
    $mockType = ['id' => 10, 'slug' => $slug, 'name' => 'Articles'];

    $this->dataTypeReadServiceMock->shouldReceive('findBySlug')
      ->once()
      ->with(Mockery::type(ShowDataTypeDTOProperities::class))
      ->andReturn($mockType);

    $response = $this->controller->show($slug);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_404_if_data_type_not_found_by_slug()
  {
    $this->dataTypeReadServiceMock->shouldReceive('findBySlug')->andReturn(null);

    $response = $this->controller->show('ghost-slug');
    $this->assertEquals(404, $response->getStatusCode());
  }

  // ==========================================
  // 4. اختبار دالة UPDATE
  // ==========================================
  #[Test]
  public function it_can_update_a_data_type_successfully()
  {
    $dataType = new DataType(['id' => 3, 'slug' => 'old-slug']);
    $requestMock = Mockery::mock(UpdateDataTypeRequest::class);
    $updateData = ['name' => 'Updated Name', 'slug' => 'updated-slug', 'is_active' => false];

    $requestMock->shouldReceive('all')->andReturn($updateData);
    $requestMock->shouldReceive('input')->andReturnUsing(function ($key, $default = null) use ($updateData) {
      return $updateData[$key] ?? $default;
    });
    // 🔥 حل مشكلة استدعاء دالة boolean() عند التحديث
    $requestMock->shouldReceive('boolean')->andReturnUsing(function ($key, $default = false) use ($updateData) {
      return isset($updateData[$key]) ? (bool)$updateData[$key] : $default;
    });

    $this->dataTypeServiceMock->shouldReceive('update')
      ->once()
      ->with($dataType, Mockery::type(UpdateDataTypeDTO::class))
      ->andReturn(array_merge($updateData, ['id' => 3]));

    $response = $this->controller->update($dataType, $requestMock);
    $this->assertEquals(200, $response->getStatusCode());
  }

  // ==========================================
  // 5. اختبار دالة DESTROY
  // ==========================================
  #[Test]
  public function it_can_soft_delete_a_data_type()
  {
    $dataType = new DataType(['id' => 3]);
    $this->dataTypeServiceMock->shouldReceive('delete')->once()->with($dataType);

    $response = $this->controller->destroy($dataType);
    $this->assertEquals(200, $response->getStatusCode());
  }

  // ==========================================
  // 6. اختبار دالة RESTORE
  // ==========================================
  #[Test]
  public function it_can_restore_a_soft_deleted_data_type()
  {
    $dataTypeId = 45;
    $this->dataTypeServiceMock->shouldReceive('restore')->once()->with($dataTypeId);

    $response = $this->controller->restore($dataTypeId);
    $this->assertEquals(200, $response->getStatusCode());
  }

  // ==========================================
  // 7. اختبار دالة FORCE DELETE
  // ==========================================
  #[Test]
  public function it_can_force_delete_a_data_type()
  {
    $dataTypeId = 45;
    $this->dataTypeServiceMock->shouldReceive('forceDelete')->once()->with($dataTypeId);

    $response = $this->controller->forceDelete($dataTypeId);
    $this->assertEquals(200, $response->getStatusCode());
  }

  // ==========================================
  // 8. اختبارات دالة TRASHED
  // ==========================================
  #[Test]
  public function it_can_list_trashed_data_types_if_they_exist()
  {
    $trashedItems = collect([['id' => 1, 'deleted_at' => now()]]);
    $this->dataTypeReadServiceMock->shouldReceive('trashed')->once()->with(1)->andReturn($trashedItems);

    $response = $this->controller->trashed();
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_404_if_no_trashed_data_types_found()
  {
    $this->dataTypeReadServiceMock->shouldReceive('trashed')->once()->with(1)->andReturn(collect([]));

    $response = $this->controller->trashed();
    $this->assertEquals(404, $response->getStatusCode());
  }
}
