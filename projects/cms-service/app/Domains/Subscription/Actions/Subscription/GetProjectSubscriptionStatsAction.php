<?php

namespace App\Domains\Subscription\Actions\Subscription;

use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use App\Models\Subscription;

class GetProjectSubscriptionStatsAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $repository
    ) {}

    /**
     * Status counts for the admin KPI row. Every known status is present in
     * the result — zero-filled — so the dashboard never has to guess whether
     * a missing key means "none" or "not reported".
     *
     * @return array{by_status: array<string, int>, total: int}
     */
    public function execute(
        int $projectId
    ): array {

        $counts = $this->repository
            ->statusCountsForProject($projectId);

        $statuses = [
            Subscription::STATUS_PENDING,
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_EXPIRED,
            Subscription::STATUS_CANCELLED,
            Subscription::STATUS_GRACE_PERIOD,
        ];

        $byStatus = [];

        foreach ($statuses as $status) {
            $byStatus[$status] = $counts[$status] ?? 0;
        }

        return [
            'by_status' => $byStatus,
            'total' => array_sum($byStatus),
        ];
    }
}
