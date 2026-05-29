<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\DataCollectionController;
use App\Domains\CMS\Services\DataCollectionService;
use App\Models\Project; // تأكد من استيراد الموديل الصحيح
use App\Models\DataCollection; // تأكد من استيراد الموديل الصحيح
use App\Domains\CMS\Requests\CreateDataCollectionRequest;
use App\Domains\CMS\Requests\UpdateDataCollectionRequest;
use App\Domains\CMS\Requests\InsertCollectionItemsRequest;
use App\Domains\CMS\Requests\RemoveCollectionItemsRequest;
use App\Domains\CMS\Requests\ReOrderCollectionItemsRequest;
use App\Domains\CMS\Requests\DeactivateCollectionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class DataCollectionControllerTest extends TestCase
{
  use RefreshDatabase;
  private MockInterface $serviceMock;
  private DataCollectionController $controller;

  protected function setUp(): void
  {
    parent::setUp();

    $this->serviceMock = $this->mock(DataCollectionService::class);
    $this->controller = new DataCollectionController($this->serviceMock);

    $project = Project::factory()->create(['public_id' => 'test-project-123']);
    $this->app->instance('currentProject', $project);
  }

  #[Test]
  public function it_can_list_collections()
  {
    // إرجاع مصفوفة غير فارغة لتجاوز فحص الـ if في الـ Controller
    $this->serviceMock->shouldReceive('list')->once()->andReturn(['test-collection']);

    $response = $this->controller->index();
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_store_collection()
  {
    // 1. البيانات المتوقعة
    $data = [
      'data_type_id' => 1,
      'name' => 'Test Collection',
      'slug' => 'test-slug',
      'type' => 'manual',
      'conditions' => [],
      'description' => 'Test',
      'is_active' => true,
      'is_offer' => false,
      'settings' => []
    ];

    $request = $this->mock(CreateDataCollectionRequest::class);

    // 2. حل المشكلة: تعريف all() ليعيد البيانات
    $request->shouldReceive('all')->andReturn($data);

    // 3. تعريف الخصائص للوصول إليها مباشرة (بما أن DTO يستخدم $request->name إلخ)
    $request->data_type_id = 1;
    $request->name = 'Test Collection';
    $request->slug = 'test-slug';
    $request->type = 'manual';
    $request->conditions = [];
    $request->conditions_logic = 'and';
    $request->description = 'Test';
    $request->is_active = true;
    $request->is_offer = false;
    $request->settings = [];

    // 4. المحاكاة الضرورية للمتطلبات الأخرى
    $request->shouldReceive('route')->andReturn(1);

    // 5. محاكاة الـ Service
    $this->serviceMock->shouldReceive('create')->once()->andReturn(['id' => 1]);

    $response = $this->controller->store($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_update_collection()
  {
    DataCollection::factory()->create(['slug' => 'test-slug']);

    $request = $this->mock(UpdateDataCollectionRequest::class);

    // 1. تعريف 'only' الذي يستخدمه الـ DTO
    $request->shouldReceive('only')->andReturn(['name' => 'Updated']);

    // 2. تعريف 'has' لـ 'name' (المطلوب في الخطأ) ولـ 'conditions'
    $request->shouldReceive('has')->with('name')->andReturn(true);
    $request->shouldReceive('has')->with('conditions')->andReturn(false);

    // 3. تعريف الخاصية 'name' لكي يتمكن الـ DTO من الوصول لـ $request->name
    $request->name = 'Updated';

    // 4. تعريف 'route'
    $request->shouldReceive('route')->with('collectionSlug')->andReturn('test-slug');

    $this->serviceMock->shouldReceive('update')->once()->andReturn(['id' => 1]);

    $response = $this->controller->update($request, 'test-slug');
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_destroy_collection()
  {
    $this->serviceMock->shouldReceive('delete')->once()->with('test-slug')->andReturn(true);

    $response = $this->controller->destroy('test-slug');

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_show_collection()
  {
    $this->serviceMock->shouldReceive('show')
      ->once()
      ->with('test-project-123', 'test-slug')
      ->andReturn(['id' => 1, 'slug' => 'test-slug']);

    $response = $this->controller->show('test-slug');

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_add_items_to_collection()
  {
    $itemsData = ['items' => [1, 2]];
    $request = $this->mock(InsertCollectionItemsRequest::class);
    $request->shouldReceive('validated')->andReturn($itemsData);

    $this->serviceMock->shouldReceive('addItems')->once();

    $response = $this->controller->addItems('test-slug', $request);
    $this->assertEquals(200, $response->getStatusCode());
  }
  #[Test]
  public function it_can_remove_items_from_collection()
  {
    $itemsData = ['items' => [1]];
    $request = $this->mock(RemoveCollectionItemsRequest::class);
    $request->shouldReceive('validated')->andReturn($itemsData);

    $this->serviceMock->shouldReceive('removeItems')->once();

    $response = $this->controller->removeItems('test-slug', $request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_reorder_items()
  {
    $itemsData = ['items' => [['item_id' => 1, 'sort_order' => 1]]];
    $request = $this->mock(ReOrderCollectionItemsRequest::class);
    $request->shouldReceive('validated')->andReturn($itemsData);

    $this->serviceMock->shouldReceive('reOrderItems')->once()->andReturn([]);

    $response = $this->controller->reorderItems('test-slug', $request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_deactivate_collection()
  {
    $request = $this->mock(DeactivateCollectionRequest::class);
    // الـ DTO يستخدم all()
    $request->shouldReceive('all')->andReturn(['is_active' => false]);
    $request->shouldReceive('validated')->andReturn(['is_active' => false]);

    $this->serviceMock->shouldReceive('deactivate')->once();

    $response = $this->controller->deactivate('test-slug', $request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_404_when_no_collections_found()
  {
    // محاكاة أن الخدمة لا تعيد شيئاً (null)
    $this->serviceMock->shouldReceive('list')->once()->andReturn(null);

    $response = $this->controller->index();
    $this->assertEquals(404, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_404_when_collection_not_found_by_slug()
  {
    // محاكاة أن الخدمة لا تجد المجموعة بالـ slug
    $this->serviceMock->shouldReceive('show')->once()->andReturn(null);

    $response = $this->controller->show('non-existent-slug');
    $this->assertEquals(404, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_404_when_collection_not_found_by_id()
  {
    // محاكاة أن الخدمة لا تجد المجموعة بالـ ID
    $this->serviceMock->shouldReceive('showById')->once()->andReturn(null);

    $response = $this->controller->showById(999);
    $this->assertEquals(404, $response->getStatusCode());
  }

  #[Test]
  public function it_can_get_entries()
  {
    $expectedEntries = ['entry_1', 'entry_2'];

    // محاكاة نجاح جلب البيانات
    $this->serviceMock->shouldReceive('getEntries')
      ->once()
      ->with('test-project-123', 'test-slug')
      ->andReturn($expectedEntries);

    $response = $this->controller->getEntries('test-slug');

    $this->assertEquals(200, $response->getStatusCode());
    // التأكد من أن الـ JSON المحتوى يطابق البيانات المتوقعة
    $this->assertEquals($expectedEntries, $response->getData(true));
  }

  #[Test]
  public function it_can_show_collection_by_id()
  {
    // 1. البيانات الوهمية التي نتوقع أن تعيدها الخدمة
    $expectedData = [
      'id' => 1,
      'name' => 'Test Collection',
      'slug' => 'test-collection'
    ];

    // 2. محاكاة الخدمة بحيث تعيد البيانات بنجاح عند طلب معرف معين
    $this->serviceMock->shouldReceive('showById')
      ->once()
      ->with(1)
      ->andReturn($expectedData);

    // 3. استدعاء التابع في الكونترولر
    $response = $this->controller->showById(1);

    // 4. التأكد من النتيجة
    $this->assertEquals(200, $response->getStatusCode());

    // التحقق من أن JSON الذي عاد يحتوي على البيانات الصحيحة في المفتاح 'data'
    $responseData = $response->getData(true);
    $this->assertEquals($expectedData, $responseData['data']);
  }
}
