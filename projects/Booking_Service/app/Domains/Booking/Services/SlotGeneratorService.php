<?php

namespace App\Domains\Booking\Services;

use App\Models\Booking;
use App\Models\Resource;
use Carbon\Carbon;

class SlotGeneratorService
{
  public function generate(Resource $resource, Carbon $date): array
  {
    $dayOfWeek   = $date->dayOfWeek; // 0=Sunday...6=Saturday
    $availability = $resource->availabilityForDay($dayOfWeek);

    // لا يوجد توفر لهذا اليوم
    if (! $availability) {
      return [];
    }

    // توليد الـ slots
    $slots        = $this->buildSlots($availability, $date);
    $bookedCounts = $this->getBookedCounts($resource->id, $date);

    // إضافة حالة كل slot
    return array_map(function (array $slot) use ($bookedCounts, $resource) {
      $key         = $slot['start'];
      $bookedCount = $bookedCounts[$key] ?? 0;
      $available   = $bookedCount < $resource->capacity
        && Carbon::parse($slot['start'])->isFuture();

      return array_merge($slot, [
        'available'   => $available,
        'booked_count' => $bookedCount,
        'capacity'    => $resource->capacity,
      ]);
    }, $slots);
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────

  private function buildSlots($availability, Carbon $date): array
  {
    $slots    = [];
    $current  = Carbon::parse($date->format('Y-m-d') . ' ' . $availability->start_time);
    $end      = Carbon::parse($date->format('Y-m-d') . ' ' . $availability->end_time);
    $duration = $availability->slot_duration; // دقائق

    while ($current->copy()->addMinutes($duration)->lte($end)) {
      $slotEnd = $current->copy()->addMinutes($duration);

      $slots[] = [
        'start' => $current->format('Y-m-d H:i:s'),
        'end'   => $slotEnd->format('Y-m-d H:i:s'),
      ];

      $current->addMinutes($duration);
    }

    return $slots;
  }

  private function getBookedCounts(int $resourceId, Carbon $date): array
  {
    $bookings = Booking::where('resource_id', $resourceId)
      ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING])
      ->whereDate('start_at', $date->format('Y-m-d'))
      ->get(['start_at']);

    $counts = [];
    foreach ($bookings as $booking) {
      $key          = $booking->start_at->format('Y-m-d H:i:s');
      $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    return $counts;
  }
}
