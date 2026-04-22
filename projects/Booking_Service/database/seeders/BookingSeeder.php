<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingCancellationPolicy;
use App\Models\Resource;
use App\Models\ResourceAvailability;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BookingSeeder extends Seeder
{
  public function run(): void
  {
    DB::transaction(function () {

      $projectId = (int) env('CMS_PROJECT_ID', 1);
      $now       = now();

      // ─── Resource 1 — غرفة اجتماعات (مدفوعة، قابلة للحجز) ────────────

      $room = Resource::create([
        'data_entry_id' => 1,
        'project_id'    => $projectId,
        'name'          => 'غرفة الاجتماعات A',
        'type'          => 'room',
        'capacity'      => 1,
        'status'        => Resource::STATUS_ACTIVE,
        'payment_type'  => Resource::PAYMENT_PAID,
        'price'         => 100.00,
        'settings'      => ['floor' => 2, 'has_projector' => true],
      ]);

      foreach ([1, 2, 3, 4, 5] as $day) {
        ResourceAvailability::create([
          'resource_id'   => $room->id,
          'day_of_week'   => $day,
          'start_time'    => '09:00:00',
          'end_time'      => '17:00:00',
          'slot_duration' => 60,
          'is_active'     => true,
        ]);
      }

      $this->createPolicies($room->id, [
        ['hours_before' => 24, 'refund_percentage' => 100, 'description' => 'إلغاء مجاني قبل 24 ساعة'],
        ['hours_before' => 12, 'refund_percentage' => 50,  'description' => 'استرداد 50% قبل 12 ساعة'],
        ['hours_before' => 0,  'refund_percentage' => 0,   'description' => 'لا استرداد أقل من 12 ساعة'],
      ]);

      // ─── Resource 2 — ملعب رياضي (مدفوع، قابل للحجز) ─────────────────

      $court = Resource::create([
        'data_entry_id' => 2,
        'project_id'    => $projectId,
        'name'          => 'الملعب الرياضي',
        'type'          => 'court',
        'capacity'      => 1,
        'status'        => Resource::STATUS_ACTIVE,
        'payment_type'  => Resource::PAYMENT_PAID,
        'price'         => 75.00,
        'settings'      => ['surface' => 'concrete', 'lighting' => true],
      ]);

      foreach (range(0, 6) as $day) {
        ResourceAvailability::create([
          'resource_id'   => $court->id,
          'day_of_week'   => $day,
          'start_time'    => '07:00:00',
          'end_time'      => '22:00:00',
          'slot_duration' => 90,
          'is_active'     => true,
        ]);
      }

      $this->createPolicies($court->id, [
        ['hours_before' => 48, 'refund_percentage' => 100, 'description' => 'إلغاء مجاني قبل 48 ساعة'],
        ['hours_before' => 24, 'refund_percentage' => 50,  'description' => 'استرداد 50% قبل 24 ساعة'],
        ['hours_before' => 0,  'refund_percentage' => 0,   'description' => 'لا استرداد أقل من 24 ساعة'],
      ]);

      // ─── Resource 3 — موعد طبيب (مجاني، قابل للحجز) ──────────────────

      $doctor = Resource::create([
        'data_entry_id' => 3,
        'project_id'    => $projectId,
        'name'          => 'د. أحمد — طب عام',
        'type'          => 'doctor',
        'capacity'      => 1,
        'status'        => Resource::STATUS_ACTIVE,
        'payment_type'  => Resource::PAYMENT_FREE,
        'price'         => null,
        'settings'      => ['specialty' => 'general'],
      ]);

      foreach ([1, 2, 3, 4] as $day) {
        ResourceAvailability::create([
          'resource_id'   => $doctor->id,
          'day_of_week'   => $day,
          'start_time'    => '09:00:00',
          'end_time'      => '15:00:00',
          'slot_duration' => 30,
          'is_active'     => true,
        ]);
      }

      ResourceAvailability::create([
        'resource_id'   => $doctor->id,
        'day_of_week'   => 5,
        'start_time'    => '09:00:00',
        'end_time'      => '12:00:00',
        'slot_duration' => 30,
        'is_active'     => false, // إجازة الجمعة
      ]);

      // لا سياسة إلغاء للمجاني — لا يوجد مبلغ يُسترد

      // ─── Resource 4 — قاعة عرض (غير قابلة للحجز) ─────────────────────

      Resource::create([
        'data_entry_id' => 4,
        'project_id'    => $projectId,
        'name'          => 'قاعة العرض',
        'type'          => 'hall',
        'capacity'      => 50,
        'status'        => Resource::STATUS_ACTIVE,
        'payment_type'  => Resource::PAYMENT_PAID,
        'price'         => 500.00,
        'settings'      => ['has_stage' => true],
      ]);

      // ─── Bookings — حجوزات تجريبية ────────────────────────────────────

      Booking::create([
        'resource_id' => $room->id,
        'user_id'     => 1,
        'project_id'  => $projectId,
        'payment_id'  => null,
        'start_at'    => $now->copy()->addDay()->setTime(10, 0),
        'end_at'      => $now->copy()->addDay()->setTime(11, 0),
        'status'      => Booking::STATUS_CONFIRMED,
        'amount'      => 100.00,
        'currency'    => 'USD',
        'notes'       => 'اجتماع فريق التطوير',
      ]);

      Booking::create([
        'resource_id' => $court->id,
        'user_id'     => 1,
        'project_id'  => $projectId,
        'payment_id'  => null,
        'start_at'    => $now->copy()->addDays(2)->setTime(18, 0),
        'end_at'      => $now->copy()->addDays(2)->setTime(19, 30),
        'status'      => Booking::STATUS_PENDING,
        'amount'      => 75.00,
        'currency'    => 'USD',
      ]);

      Booking::create([
        'resource_id'         => $room->id,
        'user_id'             => 1,
        'project_id'          => $projectId,
        'payment_id'          => null,
        'start_at'            => $now->copy()->subDay()->setTime(9, 0),
        'end_at'              => $now->copy()->subDay()->setTime(10, 0),
        'status'              => Booking::STATUS_CANCELLED,
        'amount'              => 100.00,
        'currency'            => 'USD',
        'cancellation_reason' => 'ظروف طارئة',
        'refund_amount'       => 100.00,
      ]);

      Booking::create([
        'resource_id' => $doctor->id,
        'user_id'     => 1,
        'project_id'  => $projectId,
        'payment_id'  => null,
        'start_at'    => $now->copy()->subWeek()->setTime(10, 0),
        'end_at'      => $now->copy()->subWeek()->setTime(10, 30),
        'status'      => Booking::STATUS_COMPLETED,
        'amount'      => 0.00,   // مجاني
        'currency'    => 'USD',
      ]);

      Booking::create([
        'resource_id' => $court->id,
        'user_id'     => 1,
        'project_id'  => $projectId,
        'payment_id'  => null,
        'start_at'    => $now->copy()->subDays(3)->setTime(18, 0),
        'end_at'      => $now->copy()->subDays(3)->setTime(19, 30),
        'status'      => Booking::STATUS_NO_SHOW,
        'amount'      => 75.00,
        'currency'    => 'USD',
      ]);
    });
  }

  private function createPolicies(int $resourceId, array $policies): void
  {
    foreach ($policies as $policy) {
      BookingCancellationPolicy::create([
        'resource_id'       => $resourceId,
        'hours_before'      => $policy['hours_before'],
        'refund_percentage' => $policy['refund_percentage'],
        'description'       => $policy['description'],
      ]);
    }
  }
}
