<?php

namespace App\Domains\Booking\Repositories\Eloquent;

use App\Domains\Booking\Repositories\Interface\BookingCancellationPolicyRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EloquentBookingCancellationPolicyRepository implements BookingCancellationPolicyRepositoryInterface
{
  public function getPoliciesForResource(int $resourceId)
  {
    return DB::table('booking_cancellation_policies')
      ->where('resource_id', $resourceId)
      ->orderByDesc('hours_before') // 🔥 مهم
      ->get();
  }
}
