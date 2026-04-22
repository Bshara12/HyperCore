<?php

namespace App\Domains\Core\Traits;

trait HasProjectHeaders
{
  public function projectHeaders(?string $projectId = null): array
  {
    return [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
      'X-Project-Id' => $projectId ?? request()->header('X-Project-Id'),
      'Authorization' => 'Bearer ' . request()->bearerToken(),
    ];
  }
}
