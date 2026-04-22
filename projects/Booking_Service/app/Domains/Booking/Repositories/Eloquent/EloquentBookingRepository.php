<?php

namespace App\Domains\Booking\Repositories\Eloquent;

use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class EloquentBookingRepository implements BookingRepositoryInterface
{
  public function findById(int $id): ?Booking
  {
    return Booking::with('resource')->find($id);
  }

  public function listByResource(
    int     $resourceId,
    ?string $status = null,
    ?string $from   = null,
    ?string $to     = null,
  ): Collection {
    $query = Booking::where('resource_id', $resourceId)
      ->with('resource')
      ->orderBy('start_at');

    if ($status) {
      $query->where('status', $status);
    }

    if ($from) {
      $query->where('start_at', '>=', $from);
    }

    if ($to) {
      $query->where('start_at', '<=', $to);
    }

    return $query->get();
  }

  /**
   * حجوزات مستخدم معين
   */
  public function listByUser(int $userId, ?string $status = null): Collection
  {
    $query = Booking::where('user_id', $userId)
      ->with('resource')
      ->orderByDesc('start_at');

    if ($status) {
      $query->where('status', $status);
    }

    return $query->get();
  }

  // client
  public function create(array $data): Booking
  {
    return Booking::create($data);
  }

  public function countConflictingBookings(
    int $resourceId,
    string $startAt,
    string $endAt,
    ?int $ignoreBookingId = null // 🔥 جديد

  ): int {
    // return Booking::lockForUpdate()
    //   ->where('resource_id', $resourceId)
    //   ->whereNotIn('status', ['cancelled'])
    //   ->where(function ($q) use ($startAt, $endAt) {
    //     $q->where('start_at', '<', $endAt)
    //       ->where('end_at', '>', $startAt);
    //   })
    //   ->count();
    $query = Booking::lockForUpdate()
      ->where('resource_id', $resourceId)
      ->whereNotIn('status', ['cancelled']);

    if ($ignoreBookingId) {
      $query->where('id', '!=', $ignoreBookingId);
    }

    $query->where(function ($q) use ($startAt, $endAt) {
      $q->where('start_at', '<', $endAt)
        ->where('end_at', '>', $startAt);
    });

    return $query->count();
  }

  public function getAvailabilitiesForDay(
    int $resourceId,
    int $dayOfWeek
  ) {
    return DB::table('resource_availabilities')
      ->where('resource_id', $resourceId)
      ->where('day_of_week', $dayOfWeek)
      ->where('is_active', true)
      ->get();
  }
}
