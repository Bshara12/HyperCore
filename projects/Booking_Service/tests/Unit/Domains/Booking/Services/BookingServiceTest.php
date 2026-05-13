<?php

namespace Tests\Integration\Domains\Booking\Services;

use Tests\TestCase;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\ResourceAvailability;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Booking\DTOs\Client\CreateBookingDTO;
use App\Domains\Booking\DTOs\Client\CancelBookingDTO;
use App\Domains\Booking\DTOs\Client\RescheduleBookingDTO;
use App\Domains\Booking\Read\DTOs\GetResourceSlotsDTO;
use App\Domains\Booking\Read\DTOs\GetResourceBookingsDTO;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class BookingServiceTest extends TestCase
{
  use RefreshDatabase;

  private BookingService $service;

  protected function setUp(): void
  {
    parent::setUp();
    $this->service = app(BookingService::class);
  }

  /** @test */
  public function it_fails_to_get_slots_if_resource_does_not_exist()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Resource not found.');

    // نرسل معرف غير موجود في قاعدة البيانات
    $dto = new GetResourceSlotsDTO(resourceId: 9999, date: now()->format('Y-m-d'));

    $this->service->getAvailableSlots($dto);
  }

  /** @test */
  public function it_fails_to_get_slots_if_resource_is_inactive()
  {
    // ننشئ مورد بحالة inactive
    $resource = Resource::factory()->inactive()->create();

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Resource is not active.');

    $dto = new GetResourceSlotsDTO(resourceId: $resource->id, date: now()->format('Y-m-d'));

    $this->service->getAvailableSlots($dto);
  }
  /** @test */
  public function it_fails_to_get_slots_for_past_dates()
  {
    $resource = Resource::factory()->create(['status' => 'active']);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Cannot view slots for past dates.');

    // تاريخ من العام الماضي
    $pastDate = now()->subYear()->format('Y-m-d');
    $dto = new GetResourceSlotsDTO(resourceId: $resource->id, date: $pastDate);

    $this->service->getAvailableSlots($dto);
  }
  /** @test */
  public function it_returns_available_slots_successfully_for_valid_resource_and_date()
  {
    // نستخدم withFullSetup لضمان وجود التوافر (Availability)
    $resource = Resource::factory()->withFullSetup()->create(['status' => 'active']);

    // نختبر تاريخ اليوم لضمان تجاوز شرط isPast/isToday بنجاح
    $date = now()->format('Y-m-d');
    $dto = new GetResourceSlotsDTO(resourceId: $resource->id, date: $date);

    $result = $this->service->getAvailableSlots($dto);

    // التحقق من هيكلية البيانات المرجعة
    $this->assertIsArray($result);
    $this->assertEquals($resource->id, $result['resource_id']);
    $this->assertEquals($date, $result['date']);
    $this->assertArrayHasKey('slots', $result);
    $this->assertArrayHasKey('day', $result);
  }

  /** @test */
  public function it_returns_a_collection_of_bookings_for_a_resource()
  {
    // 1. إعداد البيانات: إنشاء مورد وحجوزات مرتبطة به
    $resource = Resource::factory()->create();

    // إنشاء 3 حجوزات لهذا المورد
    Booking::factory()->count(3)->create([
      'resource_id' => $resource->id
    ]);

    // 2. تجهيز الـ DTO
    $dto = new GetResourceBookingsDTO(resourceId: $resource->id);

    // 3. التنفيذ
    $result = $this->service->getResourceBookings($dto);

    // 4. التحقق (Assertions)
    $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $result);
    $this->assertCount(3, $result);
    $this->assertEquals($resource->id, $result->first()->resource_id);
  }

  /** @test */
  public function it_returns_an_empty_collection_if_resource_has_no_bookings()
  {
    // إنشاء مورد جديد بدون أي حجوزات
    $resource = Resource::factory()->create();

    $dto = new GetResourceBookingsDTO(resourceId: $resource->id);

    $result = $this->service->getResourceBookings($dto);

    $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $result);
    $this->assertTrue($result->isEmpty());
  }

  /** @test */
  public function it_fails_to_create_booking_if_resource_not_found()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Resource not found');

    $dto = new CreateBookingDTO(
      resourceId: 9999, // ID غير موجود
      userId: 1,
      userName: 'Test User',
      projectId: 1,
      startAt: now()->addDay()->format('Y-m-d 10:00:00'),
      endAt: now()->addDay()->format('Y-m-d 11:00:00'),
      amount: 0,
      currency: 'USD',
      gateway: 'manual',
      gatewayToken: null
    );

    $this->service->create($dto);
  }

  /** @test */
  public function it_fails_to_create_booking_if_resource_is_inactive()
  {
    $resource = Resource::factory()->inactive()->create();

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Resource is inactive.');

    $dto = new CreateBookingDTO(
      resourceId: $resource->id,
      userId: 1,
      userName: 'Test User',
      projectId: 1,
      startAt: now()->addDay()->format('Y-m-d 10:00:00'),
      endAt: now()->addDay()->format('Y-m-d 11:00:00'),
      amount: 0,
      currency: 'USD',
      gateway: 'manual',
      gatewayToken: null
    );

    $this->service->create($dto);
  }

  /** @test */
  public function it_fails_if_amount_does_not_match_resource_price()
  {
    // مورد سعره 100
    $resource = Resource::factory()->paid()->create([
      'status' => 'active',
      'price' => 100.00
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Invalid booking amount');

    $dto = new CreateBookingDTO(
      resourceId: $resource->id,
      userId: 1,
      userName: 'Test User',
      projectId: 1,
      startAt: now()->addDay()->format('Y-m-d 10:00:00'),
      endAt: now()->addDay()->format('Y-m-d 11:00:00'),
      amount: 50.00, // مبلغ خاطئ
      currency: 'USD',
      gateway: 'manual',
      gatewayToken: null
    );

    $this->service->create($dto);
  }

  /** @test */
  public function it_creates_paid_booking_successfully_when_amount_is_correct()
  {
    Http::fake([
      '*/api/*' => Http::response(['status' => 'success', 'transaction_id' => 'fake_id'], 200)
    ]);
    $resource = Resource::factory()->paid()->withFullSetup()->create([
      'status' => 'active',
      'price' => 150.00
    ]);

    $date = now()->addDay()->format('Y-m-d');
    $dto = new CreateBookingDTO(
      resourceId: $resource->id,
      userId: 1,
      userName: 'Test User',
      projectId: 1,
      startAt: "$date 10:00:00",
      endAt: "$date 11:00:00",
      amount: 150.00,
      currency: 'USD',
      gateway: 'manual',
      gatewayToken: null
    );

    $result = $this->service->create($dto);

    $this->assertDatabaseHas('bookings', [
      'resource_id' => $resource->id,
      'amount' => 150.00
    ]);
  }
  /** @test */
  public function it_forces_amount_to_zero_for_free_resources_even_if_input_has_value()
  {
    $resource = Resource::factory()->withFullSetup()->create([
      'status' => 'active',
      'payment_type' => 'free',
      'price' => 0
    ]);

    $date = now()->addDay()->format('Y-m-d');
    $dto = new CreateBookingDTO(
      resourceId: $resource->id,
      userId: 1,
      userName: 'Test User',
      projectId: 1,
      startAt: "$date 10:00:00",
      endAt: "$date 11:00:00",
      amount: 999.00, // سنرسل قيمة، والخدمة يجب أن تصفرها
      currency: 'USD',
      gateway: 'manual',
      gatewayToken: null
    );

    $booking = $this->service->create($dto);

    $this->assertEquals(0, $booking->amount);
    $this->assertDatabaseHas('bookings', [
      'id' => $booking->id,
      'amount' => 0
    ]);
  }

  /** @test */
  // public function it_fails_secondary_active_check_if_status_changes()
  // {
  //   // ملاحظة: هذا السطر في كودك قد يبدو مكرراً لكن لتغطيته برمجياً:
  //   $resource = Resource::factory()->inactive()->create();
  //   $this->expectException(\Exception::class);
  //   // نتوقع الرسالة من السطر 101 في كودك (Resource inactive)
  //   $this->expectExceptionMessage('Resource is inactive');

    // $dto = new CreateBookingDTO(
    //   resourceId: $resource->id,
    //   userId: 1,
    //   userName: 'Test User',
    //   projectId: 1,
    //   startAt: now()->addDay()->format('Y-m-d 10:00:00'),
    //   endAt: now()->addDay()->format('Y-m-d 11:00:00'),
    //   amount: 0,
    //   currency: 'USD',
    //   gateway: 'manual',
    //   gatewayToken: null
    // );

  //   $this->service->create($dto);
  // }
  /** @test */
  public function it_fails_primary_active_check()
  {
    $resource = Resource::factory()->inactive()->create();

    $this->expectException(\Exception::class);
    // لاحظ الرسالة هنا تحتوي على "is" لتطابق السطر 73
    $this->expectExceptionMessage('Resource is inactive.');

    $dto = new CreateBookingDTO(
      resourceId: $resource->id,
      userId: 1,
      userName: 'Test User',
      projectId: 1,
      startAt: now()->addDay()->format('Y-m-d 10:00:00'),
      endAt: now()->addDay()->format('Y-m-d 11:00:00'),
      amount: 0,
      currency: 'USD',
      gateway: 'manual',
      gatewayToken: null
    );

    $this->service->create($dto);
  }

  /** @test */
  public function it_fails_to_cancel_if_user_is_not_the_owner()
  {
    // إنشاء حجز تابع لمستخدم رقم 1
    $booking = Booking::factory()->create(['user_id' => 1]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Unauthorized');

    // محاولة الإلغاء بواسطة مستخدم رقم 2
    $dto = new CancelBookingDTO(
      bookingId: $booking->id,
      userId: 2
    );

    $this->service->cancel($dto);
  }

  /** @test */
  public function it_cancels_booking_and_processes_refund_successfully()
  {
    // فبركة رد الـ API الخاص بالاسترداد
    \Illuminate\Support\Facades\Http::fake([
      '*/api/*' => \Illuminate\Support\Facades\Http::response(['status' => 'refunded'], 200)
    ]);

    $userId = 123;
    // إنشاء مورد مع سياسات إلغاء تسمح بالاسترداد
    $resource = Resource::factory()->withFullSetup()->create();

    $booking = Booking::factory()->create([
      'user_id' => $userId,
      'resource_id' => $resource->id,
      'amount' => 100.00,
      'payment_id' => 'ch_test_123', // وجود معرف دفع ضروري لتنفيذ شرط الـ Refund
      'status' => 'confirmed',
      'start_at' => now()->addDays(5) // موعد بعيد لضمان حساب استرداد > 0
    ]);

    $dto = new CancelBookingDTO(bookingId: $booking->id, userId: $userId);

    $result = $this->service->cancel($dto);

    // التحقق من تغيير الحالة
    $this->assertEquals('cancelled', $result->status);
    $this->assertDatabaseHas('bookings', [
      'id' => $booking->id,
      'status' => 'cancelled'
    ]);
  }

  /** @test */
  public function it_cancels_booking_without_refund_when_amount_is_zero()
  {
    $userId = 123;
    $booking = Booking::factory()->create([
      'user_id' => $userId,
      'amount' => 0, // حجز مجاني
      'payment_id' => null,
      'status' => 'confirmed'
    ]);

    $dto = new CancelBookingDTO(bookingId: $booking->id, userId: $userId);

    $result = $this->service->cancel($dto);

    $this->assertEquals('cancelled', $result->status);
    // نتحقق من أن قاعدة البيانات لم تسجل أي مبالغ مستردة إذا كان لديك حقل لذلك
  }

  /** @test */
  public function it_fails_to_reschedule_if_user_is_not_the_owner()
  {
    $booking = Booking::factory()->create(['user_id' => 1]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Unauthorized');

    $dto = new RescheduleBookingDTO(
      bookingId: $booking->id,
      userId: 2, // مستخدم مختلف
      startAt: now()->addDays(2)->format('Y-m-d 10:00:00'),
      endAt: now()->addDays(2)->format('Y-m-d 11:00:00')
    );

    $this->service->reschedule($dto);
  }

  /** @test */
  public function it_fails_to_reschedule_if_booking_is_not_confirmed()
  {
    $userId = 1;
    // حجز بحالة pending أو cancelled
    $booking = Booking::factory()->create([
      'user_id' => $userId,
      'status' => 'pending'
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Only confirmed bookings can be rescheduled');

    $dto = new RescheduleBookingDTO(
      bookingId: $booking->id,
      userId: $userId,
      startAt: now()->addDays(2)->format('Y-m-d 10:00:00'),
      endAt: now()->addDays(2)->format('Y-m-d 11:00:00')
    );

    $this->service->reschedule($dto);
  }

  /** @test */
  public function it_reschedules_successfully_and_covers_all_logic()
  {
    $userId = 1;
    $resource = Resource::factory()->withFullSetup()->create(['status' => 'active', 'capacity' => 5]);

    $booking = Booking::factory()->create([
      'user_id' => $userId,
      'resource_id' => $resource->id,
      'status' => 'confirmed',
      'start_at' => now()->addDays(2)->format('Y-m-d 10:00:00'),
      'end_at' => now()->addDays(2)->format('Y-m-d 11:00:00')
    ]);

    $newStart = now()->addDays(3)->format('Y-m-d 14:00:00');
    $dto = new RescheduleBookingDTO(
      bookingId: $booking->id,
      userId: $userId,
      startAt: $newStart,
      endAt: now()->addDays(3)->format('Y-m-d 15:00:00')
    );

    $result = $this->service->reschedule($dto);

    $this->assertEquals($newStart, $result->start_at->toDateTimeString());
  }

  /** @test */
  public function it_returns_slots_for_today_successfully()
  {
    $resource = Resource::factory()->withFullSetup()->create(['status' => 'active']);
    // تاريخ اليوم لتغطية شرط !isToday
    $date = now()->format('Y-m-d');
    $dto = new GetResourceSlotsDTO(resourceId: $resource->id, date: $date);

    $result = $this->service->getAvailableSlots($dto);
    $this->assertEquals($date, $result['date']);
  }

  /** @test */
  public function it_skips_refund_process_if_payment_id_is_missing()
  {
    $userId = 123;
    $resource = Resource::factory()->withFullSetup()->create();

    $booking = Booking::factory()->create([
      'user_id' => $userId,
      'resource_id' => $resource->id,
      'amount' => 100.00,
      'payment_id' => null, // هاد سيجعل شرط && $booking->payment_id يفشل (False)
      'status' => 'confirmed',
      'start_at' => now()->addDays(10)
    ]);

    $dto = new CancelBookingDTO(bookingId: $booking->id, userId: $userId);
    $result = $this->service->cancel($dto);

    $this->assertEquals('cancelled', $result->status);
  }
}
