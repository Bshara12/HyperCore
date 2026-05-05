<?php

namespace App\Domains\E_Commerce\Analytics\DTOs;

use Illuminate\Http\Request;

class AnalyticsFilterDTO
{
  public function __construct(
    public readonly string  $from,
    public readonly string  $to,
    public readonly string  $period,   // daily | weekly | monthly
    public readonly int     $projectId,
    public readonly int     $limit,
  ) {}

  public static function fromRequest(Request $request): self
  {
    return new self(
      from: $request->input('from', now()->subMonth()->format('Y-m-d')),
      to: $request->input('to', now()->format('Y-m-d')),
      period: in_array($request->input('period'), ['daily', 'weekly', 'monthly'])
        ? $request->input('period')
        : 'daily',
      projectId: $request->project_id,
      limit: (int) $request->input('limit', 10),
    );
  }
}
