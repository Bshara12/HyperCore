<?php

namespace App\Domains\E_Commerce\Analytics\DTOs;

use Illuminate\Http\Request;

class AnalyticsFilterDTO
{
    /** Upper bound mirrored from AnalyticsFilterRequest. */
    public const MAX_LIMIT = 100;

    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly string $period,   // daily | weekly | monthly
        public readonly int $projectId,
        public readonly int $limit,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            from: $request->input('from', now()->subMonth()->format('Y-m-d')),
            to: $request->input('to', now()->format('Y-m-d')),
            period: in_array($request->input('period'), ['daily', 'weekly', 'monthly'])
              ? $request->input('period')
              : 'daily',
            projectId: self::resolveProjectId($request),
            // Bounded: limit lands both in ->limit() and in the cache key.
            limit: min((int) $request->input('limit', 10), self::MAX_LIMIT),
        );
    }

    /**
     * The project is whatever ResolveProject resolved — never what the caller
     * asked for. That middleware merges the resolved id into the request,
     * overwriting any project_id the client sent; failing loudly when it is
     * absent keeps the guarantee from lapsing if the route ever loses it.
     */
    private static function resolveProjectId(Request $request): int
    {
        $project = $request->get('project');

        $projectId = is_array($project)
            ? ($project['id'] ?? null)
            : $request->input('project_id');

        abort_if(! $projectId, 400, 'Project could not be resolved for this request.');

        return (int) $projectId;
    }
}
