<?php

namespace App\Domains\Booking\Repositories\Interface;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Collection;

interface BookingRepositoryInterface
{
  public function findById(int $id): ?Booking;

  public function listByResource(
    int     $resourceId,
    ?string $status = null,
    ?string $from   = null,
    ?string $to     = null,
  ): Collection;

  public function listByUser(int $userId, ?string $status = null): Collection;

  // client

  public function create(array $data): Booking;

  public function countConflictingBookings(
    int $resourceId,
    string $startAt,
    string $endAt,
    //to RescheduleBookingAction
    ?int $ignoreBookingId = null // 🔥 جديد

  ): int;

  public function getAvailabilitiesForDay(
    int $resourceId,
    int $dayOfWeek
  );
}
