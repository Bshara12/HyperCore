<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\Booking\Services\BookingService;
use App\Models\Booking;
use App\Models\Resource;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class BookingControllerTest extends TestCase
{
  use RefreshDatabase, WithoutMiddleware;

  private MockInterface $serviceMock;

  protected function setUp(): void
  {
    parent::setUp();
    // عمل Mock للخدمة لعدم الاعتماد على قاعدة البيانات أو المنطق الداخلي للـ Service
    $this->serviceMock = $this->mock(BookingService::class);
  }
  #[Test]
  public function it_returns_available_slots_successfully()
  {
    // 1. إنشاء المورد في قاعدة البيانات لتجاوز فحص وجود المورد (404)
    $resource = Resource::query()->create([
      'id'            => 1,
      'name'          => 'Test Resource',
      'project_id'    => 1,
      'data_entry_id' => 1,
      'type'          => 'room',
      'status'        => 'active',
      'payment_type'  => 'paid'
    ]);

    // 2. تجهيز البيانات الوهمية
    $mockSlots = [
      '2026-05-10 09:00',
      '2026-05-10 10:00'
    ];

    // 3. توقع استدعاء الدالة داخل الـ Service
    $this->serviceMock
      ->shouldReceive('getAvailableSlots')
      ->once()
      ->andReturn($mockSlots);

    // 4. تنفيذ الطلب
    $response = $this->postJson("/api/booking/resources/{$resource->id}/slots", [
      'date' => '2026-05-10'
    ]);

    // 5. التأكد من النتيجة
    $response->assertStatus(200)
      ->assertJson([
        'data' => $mockSlots
      ]);
  }

  #[Test]
  public function it_returns_422_status_when_service_throws_exception()
  {
    // 1. إنشاء المورد لتجاوز فحص الـ 404 والوصول لمنطق الـ Service
    $resource = Resource::query()->create([
      'id'            => 1,
      'name'          => 'Test Resource',
      'project_id'    => 1,
      'data_entry_id' => 1,
      'type'          => 'room',
      'status'        => 'active',
      'payment_type'  => 'paid'
    ]);

    // 2. توقع حدوث خطأ داخل الـ Service
    $this->serviceMock
      ->shouldReceive('getAvailableSlots')
      ->once()
      ->andThrow(new \Exception("Resource not found"));

    // 3. تنفيذ الطلب
    $response = $this->postJson("/api/booking/resources/{$resource->id}/slots", [
      'date' => '2026-05-10'
    ]);

    // 4. التأكد من أن الـ Controller أرجع 422 ورسالة الخطأ
    $response->assertStatus(422)
      ->assertJson([
        'message' => 'Resource not found'
      ]);
  }

  #[Test]
  public function it_returns_resource_bookings_successfully()
  {
    // 1. إنشاء المورد في قاعدة البيانات
    $resource = Resource::query()->create([
      'id'            => 1,
      'name'          => 'Test Resource',
      'project_id'    => 1,
      'data_entry_id' => 1,
      'type'          => 'room',
      'status'        => 'active',
      'payment_type'  => 'paid'
    ]);

    // 2. تجهيز بيانات وهمية
    $mockBookings = new Collection([
      ['id' => 1, 'resource_id' => $resource->id, 'start_at' => '2026-05-10 09:00'],
      ['id' => 2, 'resource_id' => $resource->id, 'start_at' => '2026-05-10 10:00']
    ]);

    // 3. توقع استدعاء الخدمة
    $this->serviceMock
      ->shouldReceive('getResourceBookings')
      ->once()
      ->andReturn($mockBookings);

    // 4. تنفيذ الطلب
    $response = $this->postJson("/api/booking/resources/{$resource->id}/bookings", [
      'status' => 'confirmed',
      'from'   => '2026-05-01',
      'to'     => '2026-05-30'
    ]);

    // 5. التحقق من حالة الاستجابة
    $response->assertStatus(200)
      ->assertJsonCount(2, 'data')
      ->assertJson([
        'data' => $mockBookings->toArray()
      ]);
  }
  #[Test]
  public function it_stores_a_new_booking_successfully()
  {
    // 1. تجهيز مورد حقيقي مع كافة الحقول الإلزامية في قاعدة البيانات
    $resource = Resource::query()->create([
      'id'             => 1,
      'name'           => 'Test Resource',
      'project_id'     => 1, // هذا هو الحقل الذي تسبب في الخطأ
      'data_entry_id'  => 1, // أضفته بناءً على رسالة الخطأ (insert into resources...)
      'type'           => 'room',
      'status'         => 'active',
      'payment_type'   => 'paid'
    ]);

    // 2. تجهيز البيانات المتوقع عودتها من الـ Service
    $mockBooking = ['id' => 123, 'status' => 'pending'];

    $this->serviceMock
      ->shouldReceive('create')
      ->once()
      ->andReturn($mockBooking);

    // 3. حقن بيانات المستخدم يدوياً في الطلب
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', [
        'id' => 10,
        'name' => 'Mohammad'
      ]);
    });

    // 4. تنفيذ الطلب
    $response = $this->postJson('/api/create', [
      'resource_id' => $resource->id,
      'project_id'  => 1,
      'start_at'    => '2026-05-10 09:00:00',
      'end_at'      => '2026-05-10 10:00:00',
      'amount'      => 150.0,
      'currency'    => 'USD',
      'gateway'     => 'stripe',
      'token'       => 'tok_visa'
    ]);

    // 5. التحقق
    $response->assertStatus(200)
      ->assertJson([
        'data' => $mockBooking
      ]);
  }
  #[Test]
  public function it_cancels_a_booking_successfully()
  {
    // 1. إنشاء المورد (الأب)
    $resource = Resource::query()->create([
      'id'             => 1,
      'name'           => 'Test Resource',
      'project_id'     => 1, // هذا هو الحقل الذي تسبب في الخطأ
      'data_entry_id'  => 1, // أضفته بناءً على رسالة الخطأ (insert into resources...)
      'type'           => 'room',
      'status'         => 'active',
      'payment_type'   => 'paid'
    ]);

    // 2. إنشاء الحجز مع مراعاة الحقول الإلزامية في الميجريشن الخاص بك
    $booking = Booking::query()->create([
      'id'          => 123,
      'resource_id' => $resource->id,
      'user_id'     => 10,
      'project_id'  => 1,
      'start_at'    => '2026-05-10 10:00:00',
      'end_at'      => '2026-05-10 11:00:00',
      'status'      => 'confirmed', // الحالة التي تسمح بالإلغاء عادةً
      'amount'      => 100.00,      // decimal(12,2)
      'currency'    => 'USD',       // char(3)
    ]);

    // 3. تجهيز الرد الوهمي من الـ Service
    $mockResult = [
      'status'        => 'cancelled',
      'refund_amount' => 50.00,
      'booking_id'    => $booking->id
    ];

    $this->serviceMock
      ->shouldReceive('cancel')
      ->once()
      ->andReturn($mockResult);

    // 4. حقن مستخدم auth_user في الطلب لتجاوز الـ DTO Exception
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', [
        'id'   => 10,
        'name' => 'Mohammad'
      ]);
    });

    // 5. تنفيذ الطلب
    $response = $this->postJson('/api/cancel', [
      'booking_id' => $booking->id
    ]);

    // 6. التحقق
    $response->assertStatus(200)
      ->assertJson([
        'data' => $mockResult
      ]);
  }
  #[Test]
  public function it_reschedules_a_booking_successfully()
  {
    // 1. إنشاء المورد (Resource) أولاً لاحترام قيود المفتاح الخارجي
    $resource = Resource::query()->create([
      'id'             => 1,
      'name'           => 'Test Resource',
      'project_id'     => 1, // هذا هو الحقل الذي تسبب في الخطأ
      'data_entry_id'  => 1, // أضفته بناءً على رسالة الخطأ (insert into resources...)
      'type'           => 'room',
      'status'         => 'active',
      'payment_type'   => 'paid'
    ]);

    // 2. إنشاء الحجز الحالي المراد إعادة جدولة موعده
    $booking = Booking::query()->create([
      'id'          => 123,
      'resource_id' => $resource->id,
      'user_id'     => 10,
      'project_id'  => 1,
      'start_at'    => '2026-05-10 10:00:00',
      'end_at'      => '2026-05-10 11:00:00',
      'status'      => 'confirmed',
      'amount'      => 100.00,
      'currency'    => 'USD',
    ]);

    // 3. تجهيز الرد الوهمي من الـ Service بعد إعادة الجدولة
    $mockResult = [
      'booking_id' => 123,
      'new_start'  => '2026-05-11 14:00:00',
      'new_end'    => '2026-05-11 15:00:00',
      'status'     => 'rescheduled'
    ];

    $this->serviceMock
      ->shouldReceive('reschedule')
      ->once()
      ->andReturn($mockResult);

    // 4. حقن بيانات المستخدم (auth_user) في الطلب لتجاوز الـ DTO
    $this->app->make('events')->listen(\Illuminate\Routing\Events\RouteMatched::class, function ($event) {
      $event->request->attributes->set('auth_user', [
        'id'   => 10,
        'name' => 'Mohammad'
      ]);
    });

    // 5. تنفيذ طلب إعادة الجدولة بمواعيد جديدة
    $response = $this->postJson('/api/reschedule', [
      'booking_id' => $booking->id,
      'start_at'   => '2026-05-11 14:00:00',
      'end_at'     => '2026-05-11 15:00:00',
    ]);

    // 6. التحقق من النتيجة
    $response->assertStatus(200)
      ->assertJson([
        'data' => $mockResult
      ]);
  }
}
