<?php

namespace App\Domains\Booking\Actions\Client;

use App\Domains\Booking\Repositories\Interface\BookingCancellationPolicyRepositoryInterface;

// class CalculateRefundAction
// {
//   public function execute($booking): float
//   {
//     $now = now();
//     $start = \Carbon\Carbon::parse($booking->start_at);

//     $hours = $now->diffInHours($start, false);

//     // مثال policy:
//     if ($hours >= 24) {
//       return $booking->amount; // full
//     }

//     if ($hours >= 1) {
//       return $booking->amount * 0.5; // 50%
//     }

//     return 0; // no refund
//   }
// }

class CalculateRefundAction
{
  public function __construct(
    protected BookingCancellationPolicyRepositoryInterface $policyRepo
  ) {}

  public function execute($booking): float
  {
    $start = \Carbon\Carbon::parse($booking->start_at);
    $now   = now();

    $hours = $now->diffInHours($start, false);

    if ($hours <= 0) {
      return 0;
    }

    $policies = $this->policyRepo
      ->getPoliciesForResource($booking->resource_id);

    foreach ($policies as $policy) {

      if ($hours >= $policy->hours_before) {
        return ($booking->amount * $policy->refund_percentage) / 100;
      }
    }

    return 0;
  }
}
