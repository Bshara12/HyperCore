<?php

namespace App\Domains\Core\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CircuitBreakerService
{
  public function canProceed(string $service): bool
  {
    $cb = $this->getOrCreate($service);

    if ($cb->state === 'closed') {
      return true;
    }

    if ($cb->state === 'open') {

      if ($cb->next_attempt_at && Carbon::parse($cb->next_attempt_at)->isPast()) {

        $this->setState($service, 'half-open');

        return true;
      }

      return false;
    }
    // if ($cb->state === 'half-open') {
    //   return true;
    // }
    return true;
  }


  public function reportFailure(string $service): void
  {
    $cb = $this->getOrCreate($service);

    if ($cb->state === 'open') {
      return;
    }

    if ($cb->state === 'half-open') {
      DB::table('circuit_breakers')
        ->where('service_name', $service)
        ->update([
          'state' => 'open',
          'opened_at' => now(),
          'next_attempt_at' => now()->addMinutes(5),
          'updated_at' => now(),
        ]);
      return;
    }

    $failureCount = $cb->failure_count + 1;

    if ($failureCount >= $cb->failure_threshold) {
      DB::table('circuit_breakers')
        ->where('service_name', $service)
        ->update([
          'state' => 'open',
          'failure_count' => $failureCount,
          'opened_at' => now(),
          'next_attempt_at' => now()->addMinutes(5),
          'updated_at' => now(),
        ]);
    } else {
      DB::table('circuit_breakers')
        ->where('service_name', $service)
        ->update([
          'failure_count' => $failureCount,
          'updated_at' => now(),
        ]);
    }
  }


  public function reportSuccess(string $service): void
  {
    DB::table('circuit_breakers')
      ->where('service_name', $service)
      ->delete();
  }

  private function getOrCreate(string $service)
  {
    $cb = DB::table('circuit_breakers')
      ->where('service_name', $service)
      ->first();

    if (!$cb) {
      DB::table('circuit_breakers')->insert([
        'service_name' => $service,
        'state' => 'closed',
        'failure_count' => 0,
        'failure_threshold' => 5,
        'opened_at' => null,
        'next_attempt_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
      ]);

      return DB::table('circuit_breakers')
        ->where('service_name', $service)
        ->first();
    }

    return $cb;
  }

  private function setState(string $service, string $state): void
  {
    DB::table('circuit_breakers')
      ->where('service_name', $service)
      ->update([
        'state' => $state,
        'updated_at' => now(),
      ]);
  }
}
