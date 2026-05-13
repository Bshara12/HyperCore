<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\Booking\Services\ResourceService;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class ResourceControllerTest extends TestCase
{
  use RefreshDatabase, WithoutMiddleware;

  private MockInterface $serviceMock;

  protected function setUp(): void
  {
    parent::setUp();
    // عمل Mock للخدمة ResourceService
    $this->serviceMock = $this->mock(ResourceService::class);
  }
  #[Test]
  public function it_lists_resources_by_project_successfully()
  {
    // 1. تجهيز بيانات وهمية للموارد (Resources)
    $mockResources = new Collection([
      ['id' => 1, 'name' => 'Resource One', 'project_id' => 1],
      ['id' => 2, 'name' => 'Resource Two', 'project_id' => 1],
    ]);

    // 2. توقع استدعاء الدالة listByProject مع المعاملات الصحيحة
    // لاحظ أننا نتوقع استلام الـ project_id ومصفوفة المستخدم
    $this->serviceMock
      ->shouldReceive('listByProject')
      ->once()
      ->with(1, \Mockery::type('array')) // نتوقع رقم المشروع 1 ومصفوفة للمستخدم
      ->andReturn($mockResources);

    // 3. حقن بيانات المستخدم في الطلب
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', [
        'id' => 10,
        'name' => 'Mohammad',
        'role' => 'admin'
      ]);
    });

    // 4. تنفيذ الطلب مع إرسال project_id
    $response = $this->getJson('/api/booking/resources?project_id=1');
    // 5. التحقق من النتيجة
    $response->assertStatus(200)
      ->assertJsonCount(2, 'data')
      ->assertJson([
        'data' => $mockResources->toArray()
      ]);
  }
  #[Test]
  public function it_returns_a_specific_resource_successfully()
  {
    // 1. إنشاء المورد (تأكد أن الاسم هنا هو نفسه الذي ستفحصه لاحقاً)
    $resource = \App\Models\Resource::query()->create([
      'id'            => 1,
      'name'          => 'Meeting Room A', // قمنا بتوحيد الاسم هنا
      'project_id'    => 1,
      'data_entry_id' => 1,
      'type'          => 'room',    // أضفت هذه الحقول بناءً على الـ Response JSON الذي أرسلته
      'status'        => 'active',
      'payment_type'  => 'paid',
    ]);

    // 2. توقع استدعاء الخدمة وإرجاع كائن المورد
    $this->serviceMock
      ->shouldReceive('show')
      ->once()
      ->with(1)
      ->andReturn($resource);

    // 3. حقن المستخدم
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', ['id' => 1, 'name' => 'Admin']);
    });

    // 4. تنفيذ الطلب
    $response = $this->getJson('/api/booking/resources/1');

    // 5. التحقق (الآن ستتطابق البيانات)
    $response->assertStatus(200)
      ->assertJson([
        'data' => [
          'id'   => 1,
          'name' => 'Meeting Room A'
        ]
      ]);
  }
  #[Test]
  public function it_returns_404_if_resource_not_found()
  {
    // 1. توقع إرجاع null من الخدمة
    $this->serviceMock
      ->shouldReceive('show')
      ->once()
      ->with(99)
      ->andReturn(null);

    // 2. حقن المستخدم
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', ['id' => 1, 'name' => 'Admin']);
    });

    // 3. تنفيذ الطلب لرقم غير موجود
    $response = $this->getJson('/api/booking/resources/99');

    // 4. التحقق من الرسالة والكود
    $response->assertStatus(404)
      ->assertJson([
        'message' => 'Resource not found.'
      ]);
  }
  #[Test]
  public function it_creates_a_new_resource_successfully()
  {
    // 1. البيانات المرسلة
    $requestData = [
      'project_id'    => 1,
      'data_entry_id' => 101,
      'name'          => 'New Conference Room',
      'type'          => 'room',
      'capacity'      => 10,
      'payment_type'  => 'paid',
      'price'         => 50.00,
    ];

    // 2. إنشاء كائن المورد الوهمي وتحديد الـ ID يدوياً
    $mockResource = \App\Models\Resource::make($requestData);
    $mockResource->id = 5; // تعيين المعرف يدوياً ليظهر في الـ JSON
    $mockResource->status = 'active';

    // 3. توقع استدعاء الخدمة
    $this->serviceMock
      ->shouldReceive('create')
      ->once()
      ->with(\Mockery::type(\App\Domains\Booking\DTOs\ResourceDTO::class))
      ->andReturn($mockResource);

    // 4. حقن المستخدم
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', ['id' => 1, 'name' => 'Admin']);
    });

    // 5. تنفيذ الطلب
    $response = $this->postJson('/api/booking/resources', $requestData);

    // 6. التحقق
    $response->assertStatus(201)
      ->assertJson([
        'message' => 'Resource created successfully.',
        'data' => [
          'id'   => 5, // الآن سيتطابق لأننا قمنا بتعيينه في الخطوة 2
          'name' => 'New Conference Room'
        ]
      ]);
  }
  #[Test]
  public function it_updates_an_existing_resource_successfully()
  {
    // 1. إنشاء المورد وترك قاعدة البيانات تحدد الـ ID
    $resource = \App\Models\Resource::query()->create([
      'name'          => 'Old Name',
      'project_id'    => 1,
      'data_entry_id' => 1,
      'type'          => 'room',
      'status'        => 'active',
      'payment_type'  => 'free',
    ]);

    // 2. البيانات الجديدة
    $updateData = [
      'name'     => 'Updated Room Name',
      'capacity' => 20,
    ];

    // 3. تحديث الكائن في الذاكرة للمحاكاة
    $resource->name = 'Updated Room Name';
    $resource->capacity = 20;

    // 4. توقع استدعاء خدمة التحديث
    $this->serviceMock
      ->shouldReceive('update')
      ->once()
      ->with(\Mockery::type(\App\Models\Resource::class), \Mockery::type(\App\Domains\Booking\DTOs\ResourceDTO::class))
      ->andReturn($resource);

    // 5. حقن المستخدم
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', ['id' => 1, 'name' => 'Admin']);
    });

    // 6. تنفيذ الطلب باستخدام الـ ID الحقيقي الذي تم إنشاؤه
    $response = $this->patchJson("/api/booking/resources/{$resource->id}", $updateData);

    // 7. التحقق باستخدام المعرف المستخرج من قاعدة البيانات
    $response->assertStatus(200)
      ->assertJson([
        'message' => 'Resource updated successfully.',
        'data'    => [
          'id'   => $resource->id, // نستخدم المتغير هنا بدلاً من رقم ثابت
          'name' => 'Updated Room Name'
        ]
      ]);
  }
  #[Test]
  public function it_deletes_a_resource_successfully()
  {
    // 1. إنشاء مورد حقيقي في قاعدة البيانات
    $resource = \App\Models\Resource::query()->create([
      'name'          => 'Resource to Delete',
      'project_id'    => 1,
      'data_entry_id' => 1,
      'type'          => 'room',
      'status'        => 'active',
      'payment_type'  => 'free',
    ]);

    // 2. تحديث التوقعات: نقبل أي كائن من نوع Resource
    $this->serviceMock
      ->shouldReceive('delete')
      ->once()
      ->with(\Mockery::type(\App\Models\Resource::class))
      ->andReturn(true);

    // 3. حقن المستخدم
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', ['id' => 1, 'name' => 'Admin']);
    });

    // 4. تنفيذ طلب الحذف
    $response = $this->deleteJson("/api/booking/resources/{$resource->id}");

    // 5. التحقق من النتيجة
    $response->assertStatus(200)
      ->assertJson([
        'message' => 'Resource deleted successfully.'
      ]);
  }
  #[Test]
  public function it_sets_resource_availability_successfully()
  {
    // 1. إنشاء المورد
    $resource = \App\Models\Resource::query()->create([
      'name'          => 'Consultation Room',
      'project_id'    => 1,
      'data_entry_id' => 1,
      'type'          => 'room',
      'status'        => 'active',
      'payment_type'  => 'free',
    ]);

    // 2. تجهيز البيانات بناءً على متطلبات الـ Validation (422 Errors)
    $availabilities = [
      [
        'day_of_week'   => 1,        // Monday
        'start_time'    => '09:00',
        'end_time'      => '17:00',
        'slot_duration' => 30,
      ],
      [
        'day_of_week'   => 2,        // Tuesday
        'start_time'    => '09:00',
        'end_time'      => '17:00',
        'slot_duration' => 30,
      ]
    ];

    // 3. توقع استدعاء الخدمة
    $this->serviceMock
      ->shouldReceive('setAvailability')
      ->once()
      ->with(\Mockery::type(\App\Models\Resource::class), $availabilities)
      ->andReturn(true);

    // 4. حقن المستخدم
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', ['id' => 1, 'name' => 'Admin']);
    });

    // 5. تنفيذ الطلب
    $response = $this->postJson("/api/booking/resources/{$resource->id}/availability", [
      'availabilities' => $availabilities
    ]);

    // 6. التحقق (الآن سيعود 200 لأن البيانات صحيحة)
    $response->assertStatus(200)
      ->assertJson([
        'message' => 'Availability updated successfully.',
      ]);
  }
  #[Test]
  public function it_sets_resource_cancellation_policy_successfully()
  {
    // 1. إنشاء المورد
    $resource = \App\Models\Resource::query()->create([
      'name'          => 'Studio Room',
      'project_id'    => 1,
      'data_entry_id' => 1,
      'type'          => 'room',
      'status'        => 'active',
      'payment_type'  => 'paid',
    ]);

    // 2. تجهيز بيانات السياسات بناءً على أخطاء الـ Validation (422)
    $policies = [
      [
        'hours_before'      => 24, // تم التعديل من days_before إلى hours_before
        'refund_percentage' => 50,
      ],
      [
        'hours_before'      => 48,
        'refund_percentage' => 100,
      ]
    ];

    // 3. توقع استدعاء الخدمة
    $this->serviceMock
      ->shouldReceive('setPolicy')
      ->once()
      ->with(\Mockery::type(\App\Models\Resource::class), $policies)
      ->andReturn(true);

    // 4. حقن المستخدم
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', ['id' => 1, 'name' => 'Admin']);
    });

    // 5. تنفيذ الطلب
    $response = $this->postJson("/api/booking/resources/{$resource->id}/policy", [
      'policies' => $policies
    ]);

    // 6. التحقق
    $response->assertStatus(200)
      ->assertJson([
        'message' => 'Cancellation policy updated successfully.',
      ]);
  }
}
