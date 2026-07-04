<?php

namespace App\Domains\CMS\Analytics\DTOs;

use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Http\Request;

class AnalyticsFilterDTO
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $period,   // daily | weekly | monthly
        public readonly int $projectId,
        public readonly Project $project,
        public readonly int $limit,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $project_key = app('currentProject')->public_id;
        $project = app(ProjectRepositoryInterface::class)->findByKey($project_key);

        return new self(
            from: $request->input('from', now()->subMonth()->format('Y-m-d')),
            to: $request->input('to', now()->format('Y-m-d')),
            period: in_array($request->input('period'), ['daily', 'weekly', 'monthly'])
              ? $request->input('period')
              : 'daily',
            projectId: $project->id,
            project: $project,
            limit: (int) $request->input('limit', 10),
        );
    }
}
