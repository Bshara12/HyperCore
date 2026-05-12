<?php

namespace App\Domains\Booking\Actions\Client;

use App\Domains\Booking\Repositories\Interface\BookingCancellationPolicyRepositoryInterface;
use Carbon\Carbon;

class CalculateRefundAction
{
    public function __construct(
        protected BookingCancellationPolicyRepositoryInterface $policyRepo
    ) {}

    public function execute($booking): float
    {
        $start = Carbon::parse($booking->start_at);
        $now = now();

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
