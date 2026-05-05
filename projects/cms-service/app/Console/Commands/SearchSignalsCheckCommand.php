<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SearchSignalsCheckCommand extends Command
{
    protected $signature   = 'search:check-signals {--project=1}';
    protected $description = 'Check search signals health (view_count, click_count, scores)';

    public function handle(): int
    {
        $projectId = (int) $this->option('project');

        // ─── إحصائيات شاملة ──────────────────────────────────────────
        $stats = DB::table('search_indices')
            ->where('project_id', $projectId)
            ->selectRaw("
                COUNT(*) as total_rows,
                SUM(click_count)  as total_clicks,
                SUM(view_count)   as total_views,
                AVG(ctr_score)    as avg_ctr,
                AVG(popularity_score) as avg_popularity,
                AVG(freshness_score)  as avg_freshness,
                SUM(CASE WHEN view_count = 0 THEN 1 ELSE 0 END) as zero_views,
                SUM(CASE WHEN click_count = 0 THEN 1 ELSE 0 END) as zero_clicks
            ")
            ->first();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Rows',        number_format($stats->total_rows)],
                ['Total Clicks',      number_format($stats->total_clicks)],
                ['Total Views',       number_format($stats->total_views)],
                ['Avg CTR Score',     round($stats->avg_ctr, 4)],
                ['Avg Popularity',    round($stats->avg_popularity, 4)],
                ['Avg Freshness',     round($stats->avg_freshness, 4)],
                ['Rows w/ 0 Views',   number_format($stats->zero_views)],
                ['Rows w/ 0 Clicks',  number_format($stats->zero_clicks)],
            ]
        );

        // ─── Top 5 بـ click_count ──────────────────────────────────────
        $this->info("\nTop 5 by click_count:");
        $top = DB::table('search_indices')
            ->where('project_id', $projectId)
            ->select('entry_id', 'title', 'click_count', 'view_count', 'ctr_score', 'popularity_score')
            ->orderByDesc('click_count')
            ->limit(5)
            ->get();

        $this->table(
            ['Entry', 'Title', 'Clicks', 'Views', 'CTR', 'Popularity'],
            $top->map(fn($r) => [
                $r->entry_id,
                mb_substr($r->title ?? '', 0, 30),
                $r->click_count,
                $r->view_count,
                $r->ctr_score,
                $r->popularity_score,
            ])->toArray()
        );

        // ─── تحذيرات ────────────────────────────────────────────────────
        if ($stats->total_views == 0) {
            $this->error('⚠ view_count is ALWAYS 0 - IncrementViewCountJob is not being dispatched!');
            $this->line('  → Check SearchEntriesAction::dispatchViewTracking()');
            $this->line('  → Ensure queue worker is running: php artisan queue:work --queue=search-tracking');
        }

        if ($stats->avg_ctr == 0 && $stats->total_clicks > 0) {
            $this->error('⚠ ctr_score is 0 despite having clicks - Run UpdateSearchSignalsJob');
            $this->line('  → php artisan search:update-signals');
        }

        return self::SUCCESS;
    }
}