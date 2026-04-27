<?php

namespace App\Domains\CMS\Analytics\DTOs;

use Illuminate\Http\Request;

class AdminOverviewDTO
{
  public function __construct(
    public readonly string $from,
    public readonly string $to,
    public readonly string $period,
  ) {}

  public static function fromRequest(Request $request): self
  {
    return new self(
      from: $request->input('from', now()->subMonth()->format('Y-m-d')),
      to: $request->input('to', now()->format('Y-m-d')),
      period: in_array($request->input('period'), ['daily', 'weekly', 'monthly'])
        ? $request->input('period')
        : 'daily',
    );
  }
}
