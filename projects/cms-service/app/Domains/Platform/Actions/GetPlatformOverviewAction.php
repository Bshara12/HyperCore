<?php

namespace App\Domains\Platform\Actions;

use App\Models\DataEntry;
use App\Models\DataType;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Throwable;

class GetPlatformOverviewAction
{
    /**
     * Platform-wide totals for the operator dashboard.
     *
     * Counts only — no row payloads — so this stays one cheap query per metric
     * even as the platform grows.
     *
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        return [
            'projects' => $this->projectStats(),
            'content' => $this->contentStats(),
            'subscriptions' => $this->subscriptionStats(),
            'revenue' => $this->revenueStats(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function projectStats(): array
    {
        return [
            'total' => Project::query()->count(),

            // Projects live behind SoftDeletes, so the default count already
            // excludes trashed ones — report them separately rather than
            // letting them vanish from the operator's view entirely.
            'trashed' => Project::onlyTrashed()->count(),

            'owners' => Project::query()->distinct()->count('owner_id'),

            'created_last_30_days' => Project::query()
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),

            'by_module' => $this->projectsByModule(),
        ];
    }

    /**
     * enabled_modules is a JSON column, so this is counted in PHP over the
     * flag lists rather than with a GROUP BY that cannot see inside the array.
     *
     * @return array<string, int>
     */
    private function projectsByModule(): array
    {
        $counts = [];

        Project::query()
            ->select('enabled_modules')
            ->cursor()
            ->each(function ($project) use (&$counts) {

                foreach ($project->enabled_modules ?? [] as $module) {

                    if (! is_string($module)) {
                        continue;
                    }

                    $counts[$module] = ($counts[$module] ?? 0) + 1;
                }
            });

        arsort($counts);

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function contentStats(): array
    {
        return [
            'data_types' => DataType::query()->count(),
            'entries' => DataEntry::query()->count(),
            'entries_last_7_days' => DataEntry::query()
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptionStats(): array
    {
        $byStatus = Subscription::query()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        return [
            'plans' => SubscriptionPlan::query()->count(),
            'total' => array_sum($byStatus),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Paid revenue per currency. Amounts are summed in the database and cast
     * to float here, so the response carries numbers rather than driver-
     * dependent decimal strings.
     *
     * @return array<string, mixed>
     */
    private function revenueStats(): array
    {
        try {
            $paid = Payment::query()
                ->where('status', Payment::STATUS_PAID)
                ->groupBy('currency')
                ->selectRaw('currency, SUM(amount) as total, COUNT(*) as payments')
                // Aggregate rows, not Payment entities — hydrating them as
                // models would advertise columns these rows do not have.
                ->toBase()
                ->get();

            return [
                'available' => true,
                'by_currency' => $paid
                    ->map(fn ($row) => [
                        'currency' => $row->currency,
                        'total' => (float) $row->total,
                        'payments' => (int) $row->payments,
                    ])
                    ->values()
                    ->all(),
            ];
        } catch (Throwable $e) {
            // Revenue is the one block that depends on the payments schema
            // being present. A missing table should cost the operator this
            // card, not the whole overview.
            report($e);

            return [
                'available' => false,
                'by_currency' => [],
            ];
        }
    }
}
