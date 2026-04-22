<?php

namespace App\Domains\Booking\Repositories\Interface;

interface BookingCancellationPolicyRepositoryInterface
{
  public function getPoliciesForResource(int $resourceId);
}
