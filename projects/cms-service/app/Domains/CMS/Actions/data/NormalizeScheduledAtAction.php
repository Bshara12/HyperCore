<?php

namespace App\Domains\CMS\Actions\data;

use Carbon\Carbon;
use DomainException;

class NormalizeScheduledAtAction
{
  public function execute(?string $scheduledAt, string $status): ?string
  {
    if ($status !== 'scheduled') {
      return null;
    }

    if (!$scheduledAt) {
      throw new DomainException("scheduled_at is required when status is scheduled.");
    }

    return Carbon::parse($scheduledAt)->format('Y-m-d H:i:s');
  }
}
