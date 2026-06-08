<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\DataEntryController;
use App\Domains\CMS\Services\DataEntryService;
use App\Domains\CMS\Services\Versioning\VersionRestoreService;
use App\Domains\CMS\Services\FileUploadService;
use App\Domains\CMS\Requests\DataEntryRequest;
use App\Models\DataType;
use App\Models\Project; // 📥 استيراد موديل المشروع
use App\Domains\CMS\DTOs\Data\CreateDataEntryDTO;
use App\Models\DataEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile; // 📥 لمحاكاة رفع الملفات
use Symfony\Component\HttpFoundation\ParameterBag;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class DataEntryControllerTest extends TestCase
{
  use RefreshDatabase;

  private MockInterface $versionRestoreServiceMock;
  private MockInterface $dataEntryServiceMock;
  private DataEntryController $controller;
  private Project $project;

  protected function setUp(): void
  {
    parent::setUp();

    $this->versionRestoreServiceMock = $this->mock(VersionRestoreService::class);
    $this->dataEntryServiceMock = $this->mock(DataEntryService::class);

    $this->controller = new DataEntryController(
      $this->versionRestoreServiceMock,
      $this->dataEntryServiceMock
    );

    // إنشاء مشروع وربطه بالـ Container ليعمل هيلبر CurrentProject::id() بسلام
    $this->project = Project::factory()->create();
    $this->app->instance('currentProject', $this->project);
  }

  #[Test]
  public function it_can_store_a_data_entry_successfully()
  {
    // (الاختبار الحالي - يغطي حالة الـ auth_user كمصفوفة وبدون ملفات)
    $dataType = DataType::factory()->create(['slug' => 'test-dataType-slug']);
    $requestMock = Mockery::mock(DataEntryRequest::class);
    $uploaderMock = Mockery::mock(FileUploadService::class);

    $requestMock->attributes = new ParameterBag([
      'auth_user' => ['id' => 1] // هنا المصفوفة تغطي السطر 31-32
    ]);

    $requestMock->shouldReceive('input')->with('values', [])->andReturn(['field' => 'value']);
    $requestMock->shouldReceive('input')->with('status', 'draft')->andReturn('draft');
    $requestMock->shouldReceive('input')->with('status')->andReturn('draft');
    $requestMock->shouldReceive('input')->with('scheduled_at')->andReturn(null);
    $requestMock->shouldReceive('input')->with('seo')->andReturn(null);
    $requestMock->shouldReceive('input')->with('relations')->andReturn(null);
    $requestMock->shouldReceive('input')->with('slug')->andReturn('test-entry-slug');
    $requestMock->shouldReceive('filesInput')->andReturn([]);
    $requestMock->shouldReceive('projectId')->andReturn($this->project->id);

    $expectedEntry = ['id' => 12, 'slug' => 'test-entry-slug'];

    $this->dataEntryServiceMock->shouldReceive('create')
      ->once()
      ->with($this->project->id, $dataType, 'test-entry-slug', Mockery::type(CreateDataEntryDTO::class), 1)
      ->andReturn($expectedEntry);

    $response = $this->controller->store($requestMock, $dataType, $uploaderMock);

    $this->assertEquals(201, $response->getStatusCode());
  }

  #[Test]
  public function it_can_store_entry_when_auth_user_is_an_object()
  {
    // 🎯 المستهدف: تغطية الأسطر 33-35 (elseif الخاص بالـ Object)
    $dataType = DataType::factory()->create();
    $requestMock = Mockery::mock(DataEntryRequest::class);
    $uploaderMock = Mockery::mock(FileUploadService::class);

    // نمرر الـ auth_user ككائن (Object) بدلاً من مصفوفة لدخول فرع الـ elseif
    $requestMock->attributes = new ParameterBag([
      'auth_user' => (object) ['id' => 99]
    ]);

    $requestMock->shouldReceive('input')->with('values', [])->andReturn([]);
    $requestMock->shouldReceive('input')->with('status', 'draft')->andReturn('draft');
    $requestMock->shouldReceive('input')->with('status')->andReturn('draft');
    $requestMock->shouldReceive('input')->with('scheduled_at')->andReturn(null);
    $requestMock->shouldReceive('input')->with('seo')->andReturn(null);
    $requestMock->shouldReceive('input')->with('relations')->andReturn(null);
    $requestMock->shouldReceive('input')->with('slug')->andReturn('object-user-entry');
    $requestMock->shouldReceive('filesInput')->andReturn([]);
    $requestMock->shouldReceive('projectId')->andReturn($this->project->id);

    $this->dataEntryServiceMock->shouldReceive('create')
      ->once()
      ->with($this->project->id, $dataType, 'object-user-entry', Mockery::type(CreateDataEntryDTO::class), 99) // نتوقع المعرف 99
      ->andReturn(['id' => 13]);

    $response = $this->controller->store($requestMock, $dataType, $uploaderMock);
    $this->assertEquals(201, $response->getStatusCode());
  }

  #[Test]
  public function it_can_store_entry_and_handle_nested_file_uploads()
  {
    // 🎯 المستهدف: تغطية الأسطر 41-55 (حلقات الـ foreach لرفع الملفات)
    $dataType = DataType::factory()->create();
    $requestMock = Mockery::mock(DataEntryRequest::class);
    $uploaderMock = Mockery::mock(FileUploadService::class);

    $requestMock->attributes = new ParameterBag(['auth_user' => ['id' => 1]]);

    $requestMock->shouldReceive('input')->with('values', [])->andReturn(['existing_field' => 'data']);
    $requestMock->shouldReceive('input')->with('status', 'draft')->andReturn('draft');
    $requestMock->shouldReceive('input')->with('status')->andReturn('draft');
    $requestMock->shouldReceive('input')->with('scheduled_at')->andReturn(null);
    $requestMock->shouldReceive('input')->with('seo')->andReturn(null);
    $requestMock->shouldReceive('input')->with('relations')->andReturn(null);
    $requestMock->shouldReceive('input')->with('slug')->andReturn('file-entry');
    $requestMock->shouldReceive('projectId')->andReturn($this->project->id);

    // ✅ هنا التعديل: إنشاء ملف وهمي بدون الحاجة لـ GD extension
    $fakeFile = UploadedFile::fake()->create('avatar.jpg', 100);

    $requestMock->shouldReceive('filesInput')->andReturn([
      '10' => [
        'en' => [
          $fakeFile
        ]
      ]
    ]);

    $uploaderMock->shouldReceive('upload')
      ->once()
      ->with($fakeFile, $this->project->id, $dataType->id, 10)
      ->andReturn('uploads/projects/1/avatar.jpg');

    $this->dataEntryServiceMock->shouldReceive('create')
      ->once()
      ->with($this->project->id, $dataType, 'file-entry', Mockery::type(CreateDataEntryDTO::class), 1)
      ->andReturn(['id' => 14]);

    $response = $this->controller->store($requestMock, $dataType, $uploaderMock);
    $this->assertEquals(201, $response->getStatusCode());
  }

  #[Test]
  public function it_can_store_entry_with_scheduled_status()
  {
    // 🎯 المستهدف: تغطية الأسطر 63-67 (منطق جدولة المنشورات scheduled)
    $dataType = DataType::factory()->create();
    $requestMock = Mockery::mock(DataEntryRequest::class);
    $uploaderMock = Mockery::mock(FileUploadService::class);

    $requestMock->attributes = new ParameterBag(['auth_user' => ['id' => 1]]);

    $requestMock->shouldReceive('input')->with('values', [])->andReturn(['field' => 'value']);

    // 🔥 محاكاة إرجاع الحالة كمجدولة لدخول جملة الـ if
    $requestMock->shouldReceive('input')->with('status')->andReturn('scheduled');
    $requestMock->shouldReceive('input')->with('status', 'draft')->andReturn('scheduled');

    // تمرير تاريخ بصيغة نصية ليقوم الكنترولر بعمل Parse له
    $scheduledDate = '2026-06-01 15:30:00';
    $requestMock->shouldReceive('input')->with('scheduled_at')->andReturn($scheduledDate);

    $requestMock->shouldReceive('input')->with('seo')->andReturn(null);
    $requestMock->shouldReceive('input')->with('relations')->andReturn(null);
    $requestMock->shouldReceive('input')->with('slug')->andReturn('scheduled-entry');
    $requestMock->shouldReceive('filesInput')->andReturn([]);
    $requestMock->shouldReceive('projectId')->andReturn($this->project->id);

    // التحقق الذكي: نضمن أن الـ DTO المتولد مرر للخدمة وبداخله البيانات والتاريخ المفرمت صح
    $this->dataEntryServiceMock->shouldReceive('create')
      ->once()
      ->with(
        $this->project->id,
        $dataType,
        'scheduled-entry',
        Mockery::on(function (CreateDataEntryDTO $dto) use ($scheduledDate) {
          return $dto->status === 'scheduled' && $dto->scheduled_at === $scheduledDate;
        }),
        1
      )
      ->andReturn(['id' => 15, 'status' => 'scheduled', 'scheduled_at' => $scheduledDate]);

    // التنفيذ الفعلي
    $response = $this->controller->store($requestMock, $dataType, $uploaderMock);

    // التأكيدات
    $this->assertEquals(201, $response->getStatusCode());
  }

  #[Test]
  public function it_can_update_entry_when_auth_user_is_an_object()
  {
    // 🎯 المستهدف: تغطية الأسطر 95-97 (حالة الـ elseif عندما يكون المستخدم كائناً)
    $dataType = DataType::factory()->create();
    $entrySlug = 'object-user-update-slug';
    $entry = DataEntry::factory()->create([
      'slug' => $entrySlug,
      'data_type_id' => $dataType->id
    ]);

    $requestMock = Mockery::mock(DataEntryRequest::class);

    // نمرر الـ auth_user ككائن (Object) حصراً لدخول الـ elseif
    $requestMock->attributes = new ParameterBag([
      'auth_user' => (object) ['id' => 7]
    ]);

    $requestMock->shouldReceive('input')->andReturnUsing(function ($key, $default = null) {
      return match ($key) {
        'values' => ['title' => 'Updated by Object User'],
        'status' => 'published',
        'seo' => null,
        'relations' => null,
        'scheduled_at' => null,
        default => $default,
      };
    });

    $updatedEntry = ['id' => $entry->id, 'slug' => $entrySlug];

    $this->dataEntryServiceMock->shouldReceive('update')
      ->once()
      ->with($requestMock, Mockery::type(CreateDataEntryDTO::class), 7) // نتوقع المعرف 7
      ->andReturn($updatedEntry);

    $response = $this->controller->update($requestMock, $dataType, $entrySlug);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_update_entry_when_auth_user_is_an_array()
  {
    // 🎯 المستهدف: تغطية الأسطر 93-94 (حالة الـ if عندما يكون المستخدم مصفوفة)
    $dataType = DataType::factory()->create();
    $entrySlug = 'array-user-update-slug';
    $entry = DataEntry::factory()->create([
      'slug' => $entrySlug,
      'data_type_id' => $dataType->id
    ]);

    $requestMock = Mockery::mock(DataEntryRequest::class);

    // نمرر الـ auth_user كمصفوفة (Array) لدخول السطر 93
    $requestMock->attributes = new ParameterBag([
      'auth_user' => ['id' => 99]
    ]);

    $requestMock->shouldReceive('input')->andReturnUsing(function ($key, $default = null) {
      return match ($key) {
        'values' => ['title' => 'Updated by Array User'],
        'status' => 'published',
        'seo' => null,
        'relations' => null,
        'scheduled_at' => null,
        default => $default,
      };
    });

    $updatedEntry = ['id' => $entry->id, 'slug' => $entrySlug];

    $this->dataEntryServiceMock->shouldReceive('update')
      ->once()
      ->with($requestMock, Mockery::type(CreateDataEntryDTO::class), 99) // نتوقع المعرف 99
      ->andReturn($updatedEntry);

    $response = $this->controller->update($requestMock, $dataType, $entrySlug);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_delete_a_data_entry_successfully()
  {
    // 🎯 المسار الأول: الحذف الناجح (Happy Path)
    $projectId = $this->project->id;
    $entrySlug = 'slug-to-be-deleted';

    // 1. تجهيز السجل في قاعدة البيانات المؤقتة لكي يعثر عليه استعلام firstOrFail
    $entryModel = DataEntry::factory()->create([
      'project_id' => $projectId,
      'slug' => $entrySlug
    ]);

    // إذا كانت الكلاس CurrentProject عبارة عن Facade، يمكنك عمل Mock لها هنا للتأكد من إرجاع الـ ID
    // CurrentProject::shouldReceive('id')->andReturn($projectId);
    // (ملاحظة: لو كانت تعتمد على جلسة أو دالة مساعدة تعمل تلقائياً في البيئة لديك، يمكنك إبقاء الكود كما هو)

    // 2. توقعات الـ Service: نتوقع استدعاء دالة الحذف مرة واحدة بالمعرفات الصحيحة
    $this->dataEntryServiceMock->shouldReceive('destroy')
      ->once()
      ->with($entryModel->id, $projectId);

    // 3. التنفيذ الفعلي
    $response = $this->controller->destroy($entrySlug);

    // 4. التأكيدات (Assertions)
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(json_encode([
      'message' => 'Data deleted successfully'
    ]), $response->getContent());
  }

  #[Test]
  public function it_throws_model_not_found_exception_if_entry_does_not_exist()
  {
    // 🎯 المسار الثاني: محاولة حذف سجل غير موجود (تغطية الـ Fail في firstOrFail)

    // إعلام أداة الاختبار بأننا نتوقع رمي هذا الخطأ تلقائياً من لارافيل
    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    // التنفيذ: استدعاء الدالة بـ slug وهمي تماماً وغير موجود بالقاعدة
    $this->controller->destroy('non-existent-slug');
  }

  #[Test]
  public function it_can_delete_a_data_entry_by_type_successfully()
  {
    // 🎯 المسار الأول: الحذف الناجح بناءً على النوع (Happy Path)
    $dataType = DataType::factory()->create();
    $projectId = $this->project->id;
    $entrySlug = 'slug-by-type-to-delete';

    // 1. تجهيز السجل في قاعدة البيانات ليعثر عليه استعلام firstOrFail
    $entryModel = DataEntry::factory()->create([
      'project_id' => $projectId,
      'slug' => $entrySlug,
      'data_type_id' => $dataType->id
    ]);

    // 2. توقعات الـ Service: نضمن استدعاء دالة الحذف بالمعرفات الصحيحة
    $this->dataEntryServiceMock->shouldReceive('destroy')
      ->once()
      ->with($entryModel->id, $projectId);

    // 3. التنفيذ الفعلي (نمرر كائن الـ dataType والـ slug)
    $response = $this->controller->destroyByType($dataType, $entrySlug);

    // 4. التأكيدات (Assertions)
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(json_encode([
      'message' => 'Data deleted successfully'
    ]), $response->getContent());
  }

  #[Test]
  public function it_throws_model_not_found_exception_if_entry_by_type_does_not_exist()
  {
    // 🎯 المسار الثاني: محاولة حذف سجل غير موجود (تغطية الـ Fail في firstOrFail)
    $dataType = DataType::factory()->create();

    // إعلام Pest بأننا نتوقع رمي استثناء ModelNotFoundException
    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    // التنفيذ: استدعاء الدالة بـ slug وهمي تماماً
    $this->controller->destroyByType($dataType, 'non-existent-type-slug');
  }

  #[Test]
  public function it_can_restore_a_version_successfully()
  {
    // 1. تجهيز المعرفات الوهمية للاختبار
    $versionId = 42;
    $userId = 9;

    // 2. محاكاة الـ Request والـ auth_user ككائن يحتوي على id
    $requestMock = Mockery::mock(\Illuminate\Http\Request::class);

    $requestMock->attributes = new \Symfony\Component\HttpFoundation\ParameterBag([
      'auth_user' => (object) ['id' => $userId]
    ]);

    // 3. توقعات خدمة استعادة النسخ (versionRestoreService)
    // 💡 ملاحظة: تأكد أن اسم الـ Mock الخاص بهذه الخدمة يطابق الاسم المعرّف عندك في دالة setUp
    $this->versionRestoreServiceMock->shouldReceive('restore')
      ->once()
      ->with($versionId, $userId);

    // 4. التنفيذ الفعلي باستدعاء دالة الـ restore
    $response = $this->controller->restore($requestMock, $versionId);

    // 5. التأكيدات (Assertions)
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals(json_encode([
      'message' => 'Version restored successfully',
    ]), $response->getContent());
  }
}
