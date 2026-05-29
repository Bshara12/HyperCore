<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\FieldController;
use App\Domains\CMS\Services\FieldService;
use App\Domains\CMS\Read\Services\DataTypeFieldService;
use App\Domains\CMS\Requests\CreateFieldRequest;
use App\Models\DataType;
use App\Models\DataTypeField;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class FieldControllerTest extends TestCase
{
  private FieldController $controller;
  private $fieldServiceMock;
  private $dataTypeFieldServiceMock;

  protected function setUp(): void
  {
    parent::setUp();

    // محاكاة الخدمات (Services) فقط لعزل العمليات عن قاعدة البيانات
    $this->fieldServiceMock = Mockery::mock(FieldService::class);
    $this->dataTypeFieldServiceMock = Mockery::mock(DataTypeFieldService::class);

    $this->controller = new FieldController($this->fieldServiceMock, $this->dataTypeFieldServiceMock);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  // ==========================================
  // 1. اختبار دالة STORE
  // ==========================================
  #[Test]
  public function it_can_store_a_new_field_successfully()
  {
    $dataTypeId = 10;

    // إنشاء Request حقيقي بالبيانات المتوافقة مع الـ Validation Rules
    $request = CreateFieldRequest::create('/fields', 'POST', [
      'name' => 'عنوان المقال',
      'type' => 'text',
      'required' => true,
    ]);

    $mockCreatedField = ['id' => 1, 'name' => 'عنوان المقال', 'type' => 'text'];

    // توقع استدعاء دالة create داخل الـ Service وتمرير الـ DTO المتولد تلقائياً
    $this->fieldServiceMock->shouldReceive('create')
      ->once()
      ->with(Mockery::any()) // يطابق الـ DTO المنشأ داخلياً
      ->andReturn($mockCreatedField);

    $response = $this->controller->store($request, $dataTypeId);

    $this->assertEquals(201, $response->getStatusCode());
    $this->assertStringContainsString('Field created successfully', $response->getContent());
  }

  // ==========================================
  // 2. اختبار دالة INDEX
  // ==========================================
  #[Test]
  public function it_can_list_fields_by_data_type()
  {
    // إنشاء Model حقيقي وتعيين معرف له دون الحاجة لـ Mock
    $dataType = new DataType();
    $dataType->id = 5;

    $mockList = [
      ['id' => 1, 'name' => 'Field 1'],
      ['id' => 2, 'name' => 'Field 2']
    ];

    $this->dataTypeFieldServiceMock->shouldReceive('list')
      ->once()
      ->with($dataType)
      ->andReturn($mockList);

    $response = $this->controller->index($dataType);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(json_encode($mockList), $response->getContent());
  }

  // ==========================================
  // 3. اختبار دالة UPDATE
  // ==========================================
  #[Test]
  public function it_can_update_an_existing_field_successfully()
  {
    $field = new DataTypeField();
    $field->id = 25;
    $field->data_type_id = 10; // 🌟 الحل هنا: تحديد الـ data_type_id حتى يتمكن الـ DTO من قراءته بنجاح

    $request = CreateFieldRequest::create('/fields/25', 'PUT', [
      'name' => 'الاسم المحدث',
      'type' => 'string'
    ]);

    $mockUpdatedField = ['id' => 25, 'name' => 'الاسم المحدث', 'type' => 'string'];

    $this->fieldServiceMock->shouldReceive('update')
      ->once()
      ->with($field, Mockery::any())
      ->andReturn($mockUpdatedField);

    $response = $this->controller->update($request, $field);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Field updated successfully', $response->getContent());
  }

  // ==========================================
  // 4. اختبار دالة DESTROY
  // ==========================================
  #[Test]
  public function it_can_soft_delete_a_field()
  {
    $field = new DataTypeField();
    $field->id = 40;

    $this->fieldServiceMock->shouldReceive('destroy')
      ->once()
      ->with($field)
      ->andReturn(true);

    $response = $this->controller->destroy($field);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Data-Type Field deleted successfully', $response->getContent());
  }

  // ==========================================
  // 5. اختبار دالة RESTORE
  // ==========================================
  #[Test]
  public function it_can_restore_a_soft_deleted_field()
  {
    $fieldId = 40;

    $this->fieldServiceMock->shouldReceive('restore')
      ->once()
      ->with($fieldId)
      ->andReturn(true);

    $response = $this->controller->restore($fieldId);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Data-Type Field restored successfully', $response->getContent());
  }

  // ==========================================
  // 6. اختبار دالة TRASHED (في حال وجود عناصر محذوفة)
  // ==========================================
  #[Test]
  public function it_returns_trashed_fields_if_they_exist()
  {
    $dataType = new DataType();
    $dataType->id = 7;

    // محاكاة تجميعة لارافيل (Collection) تحتوي على عناصر حقيقية ممسوحة
    $mockTrashedCollection = collect([
      ['id' => 1, 'deleted_at' => '2026-05-29']
    ]);

    $this->dataTypeFieldServiceMock->shouldReceive('trashed')
      ->once()
      ->with($dataType)
      ->andReturn($mockTrashedCollection);

    $response = $this->controller->trashed($dataType);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals($mockTrashedCollection->toJson(), $response->getContent());
  }

  // ==========================================
  // 7. اختبار دالة TRASHED (في حال عدم وجود عناصر محذوفة -> 404)
  // ==========================================
  #[Test]
  public function it_returns_404_if_no_trashed_fields_found()
  {
    $dataType = new DataType();
    $dataType->id = 7;

    // محاكاة تجميعة فارغة لـتفعيل شرط الـ isEmpty
    $mockEmptyCollection = collect([]);

    $this->dataTypeFieldServiceMock->shouldReceive('trashed')
      ->once()
      ->with($dataType)
      ->andReturn($mockEmptyCollection);

    $response = $this->controller->trashed($dataType);

    $this->assertEquals(404, $response->getStatusCode());
    $this->assertStringContainsString('No trashed Data-Type Fields found', $response->getContent());
  }

  // ==========================================
  // 8. اختبار دالة FORCE DELETE
  // ==========================================
  #[Test]
  public function it_can_force_delete_a_field_permanently()
  {
    $fieldId = 99;

    $this->fieldServiceMock->shouldReceive('forceDelete')
      ->once()
      ->with($fieldId)
      ->andReturn(true);

    $response = $this->controller->forceDelete($fieldId);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Data-Type Field force deleted successfully', $response->getContent());
  }
}
