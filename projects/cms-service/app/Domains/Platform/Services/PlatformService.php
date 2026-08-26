<?php

namespace App\Domains\Platform\Services;

use App\Domains\Platform\Actions\GetPlatformAuditLogsAction;
use App\Domains\Platform\Actions\GetPlatformLogsAction;
use App\Domains\Platform\Actions\GetPlatformOverviewAction;
use App\Domains\Platform\Actions\GetSystemHealthAction;
use App\Domains\Platform\Actions\ListAllProjectsAction;
use Illuminate\Pagination\LengthAwarePaginator;

class PlatformService
{
    public function __construct(
        private GetPlatformOverviewAction $overviewAction,
        private GetSystemHealthAction $healthAction,
        private ListAllProjectsAction $listProjectsAction,
        private GetPlatformLogsAction $logsAction,
        private GetPlatformAuditLogsAction $auditLogsAction
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        return $this->overviewAction->execute();
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->healthAction->execute();
    }

    public function projects(
        ?string $search,
        ?string $module,
        ?int $ownerId,
        bool $includeTrashed,
        int $perPage
    ): LengthAwarePaginator {

        return $this->listProjectsAction->execute(
            search: $search,
            module: $module,
            ownerId: $ownerId,
            includeTrashed: $includeTrashed,
            perPage: $perPage
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function logs(array $filters): array
    {
        return $this->logsAction->execute($filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function auditLogs(): array
    {
        return $this->auditLogsAction->execute();
    }
}
