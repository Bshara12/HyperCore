<?php

namespace Tests\Integration\Domains\Booking\Services;

use Tests\TestCase;
use App\Models\Resource;
use App\Domains\Booking\Services\ResourceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

class ResourceServiceTest extends TestCase
{
  use RefreshDatabase;

  private ResourceService $service;

  protected function setUp(): void
  {
    parent::setUp();
    $this->service = app(ResourceService::class);
  }

  /**
   * 1. Test listByProject
   * الهدف: تغطية استدعاء IndexResourcesAction بنجاح
   */
  /** @test */
  public function it_can_list_resources_by_project_with_correct_user_structure()
  {
    // إعداد: إنشاء مشروع وموارد تابعة له
    $projectId = 1;
    Resource::factory()->count(2)->create(['project_id' => $projectId]);

    // إعداد هيكلية المستخدم المتوقعة للأكشن (لتجنب خطأ roles)
    $user = [
      'id' => 1,
      'roles' => [
        ['name' => 'admin']
      ]
    ];

    // التنفيذ
    $result = $this->service->listByProject($projectId, $user);

    // التحقق
    $this->assertInstanceOf(Collection::class, $result);
    $this->assertCount(2, $result);
    $this->assertEquals($projectId, $result->first()->project_id);
  }

  /**
   * 2. Test show
   * الهدف: التأكد من جلب مورد معين أو إعادة null في حال عدم الوجود
   */
  /** @test */
  public function it_can_show_a_specific_resource_by_id()
  {
    // إعداد: إنشاء مورد
    $resource = Resource::factory()->create();

    // التنفيذ
    $result = $this->service->show($resource->id);

    // التحقق
    $this->assertInstanceOf(Resource::class, $result);
    $this->assertEquals($resource->id, $result->id);
  }

  /** @test */
  public function it_returns_null_when_showing_non_existent_resource()
  {
    // التنفيذ باستخدام معرف غير موجود
    $result = $this->service->show(99999);

    // التحقق
    $this->assertNull($result);
  }

  /**
   * 3. Test create
   * الهدف: التأكد من تحويل الـ DTO إلى سجل حقيقي في قاعدة البيانات
   */
  /** @test */
  public function it_can_create_a_resource_successfully()
  {
    // 1. إعداد البيانات للـ DTO
    $dto = new \App\Domains\Booking\DTOs\ResourceDTO(
      name: 'Conference Room A',
      type: 'room',
      capacity: 15,
      status: 'active',
      projectId: 1,
      dataEntryId: 1,
      paymentType: 'paid',
      price: 250.00,
      settings: ['wifi' => true, 'projector' => true]
    );

    // 2. التنفيذ
    $result = $this->service->create($dto);

    // 3. التحقق (Assertions)
    $this->assertInstanceOf(Resource::class, $result);
    $this->assertEquals('Conference Room A', $result->name);
    $this->assertEquals(15, $result->capacity);

    // التأكد من وجود السجل فعلياً في قاعدة البيانات
    $this->assertDatabaseHas('resources', [
      'name' => 'Conference Room A',
      'project_id' => 1,
      'price' => 250.00
    ]);
  }

  /**
   * 4. Test update
   * الهدف: التأكد من تحديث بيانات مورد موجود مسبقاً بناءً على الـ DTO
   */
  /** @test */
  public function it_can_update_an_existing_resource()
  {
    // 1. إعداد: إنشاء مورد قديم في قاعدة البيانات
    $resource = Resource::factory()->create([
      'name' => 'Old Room Name',
      'capacity' => 5
    ]);

    // 2. تجهيز الـ DTO بالبيانات الجديدة
    $dto = new \App\Domains\Booking\DTOs\ResourceDTO(
      name: 'Updated Room Name',
      type: $resource->type,
      capacity: 10, // تغيير السعة
      status: 'active',
      projectId: $resource->project_id,
      dataEntryId: $resource->data_entry_id,
      paymentType: $resource->payment_type,
      price: $resource->price,
      settings: $resource->settings ?? []
    );

    // 3. التنفيذ
    $result = $this->service->update($resource, $dto);

    // 4. التحقق
    $this->assertInstanceOf(Resource::class, $result);
    $this->assertEquals('Updated Room Name', $result->name);
    $this->assertEquals(10, $result->capacity);

    // التأكد من التحديث في قاعدة البيانات
    $this->assertDatabaseHas('resources', [
      'id' => $resource->id,
      'name' => 'Updated Room Name',
      'capacity' => 10
    ]);

    $this->assertDatabaseMissing('resources', [
      'name' => 'Old Room Name'
    ]);
  }

  /**
   * 5. Test delete
   * الهدف: التأكد من حذف المورد من قاعدة البيانات بنجاح
   */
  /** @test */
  public function it_can_delete_a_resource_successfully()
  {
    // 1. إعداد: إنشاء مورد للحذف
    $resource = Resource::factory()->create();

    // 2. التنفيذ
    $this->service->delete($resource);

    // 3. التحقق (Assertion)

    // إذا كنت تستخدم Soft Deletes (الأرجح في Laravel):
    $this->assertSoftDeleted($resource);

    // إذا كنت تستخدم الحذف النهائي، استخدم هذا السطر بدلاً من الأعلى:
    // $this->assertDatabaseMissing('resources', ['id' => $resource->id]);
  }

  /**
   * 6. Test setAvailability
   * الهدف: التأكد من تمرير مصفوفة التوافر للأكشن وحفظها للمورد
   */
  /** @test */
  public function it_can_set_availability_for_a_resource()
  {
    // 1. إعداد: إنشاء مورد
    $resource = Resource::factory()->create();

    // 2. تجهيز مصفوفة التوافر (بالهيكلية التي يتوقعها AvailabilityDTO)
    $availabilities = [
      [
        'day_of_week' => 1, // Monday
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'slot_duration' => 60,
        'is_active' => true
      ],
      [
        'day_of_week' => 2, // Tuesday
        'start_time' => '10:00:00',
        'end_time' => '18:00:00',
        'slot_duration' => 30,
        'is_active' => true
      ]
    ];

    // 3. التنفيذ
    $this->service->setAvailability($resource, $availabilities);

    // 4. التحقق
    // نتحقق أن البيانات تم تخزينها في جدول التوافر المرتبط بالمورد
    $this->assertDatabaseHas('resource_availabilities', [
      'resource_id' => $resource->id,
      'day_of_week' => 1,
      'slot_duration' => 60
    ]);

    $this->assertDatabaseHas('resource_availabilities', [
      'resource_id' => $resource->id,
      'day_of_week' => 2,
      'slot_duration' => 30
    ]);
  }

  /**
   * 7. Test setPolicy
   * الهدف: التأكد من ضبط سياسات الإلغاء والاسترداد للمورد
   */
  /** @test */
  public function it_can_set_cancellation_policy_for_a_resource()
  {
    // 1. إعداد: إنشاء مورد
    $resource = Resource::factory()->create();

    // 2. تجهيز مصفوفة السياسات (Cancellation Policies)
    // نفترض الهيكلية الشائعة: عدد الساعات قبل الحجز ونسبة الاسترداد
    $policies = [
      [
        'hours_before' => 24,
        'refund_percentage' => 100,
      ],
      [
        'hours_before' => 12,
        'refund_percentage' => 50,
      ]
    ];

    // 3. التنفيذ
    $this->service->setPolicy($resource, $policies);

    // 4. التحقق
    // نتحقق من وجود السياسات في الجدول المخصص لها (غالباً booking_cancellation_policies)
    $this->assertDatabaseHas('booking_cancellation_policies', [
      'resource_id' => $resource->id,
      'hours_before' => 24,
      'refund_percentage' => 100
    ]);

    $this->assertDatabaseHas('booking_cancellation_policies', [
      'resource_id' => $resource->id,
      'hours_before' => 12,
      'refund_percentage' => 50
    ]);
  }
}
