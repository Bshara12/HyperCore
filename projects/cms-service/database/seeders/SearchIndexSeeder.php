<?php

namespace Database\Seeders;

use App\Domains\Search\Actions\ReindexSearchAction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Builds the search index from the content the project seeders actually created.
 *
 * This used to be ~32KB of hand-written search_indices rows carrying
 * `project_id => 1` and `data_type_id => 1|2|3`. Those ids were only ever
 * correct on a database seeded from empty in one specific order, and the rows
 * pointed at `entry_id` values that did not necessarily exist — so search
 * returned records with no entry behind them, and every project other than the
 * first had no index at all.
 *
 * Now it runs the same ReindexSearchAction the `search:reindex` command uses,
 * which walks every published entry and derives project_id, data_type_id,
 * language, title and content from the real record. Consequences:
 *
 *  - every seeded project gets its own index, automatically
 *  - a search hit always resolves back to a real entry
 *  - the index reflects whatever the content seeders produced, so the two can
 *    never drift apart again
 *
 * Must run AFTER the content seeders.
 */
class SearchIndexSeeder extends Seeder
{
    public function __construct(
        private ReindexSearchAction $reindex
    ) {}

    public function run(): void
    {
        $publishable = DB::table('data_entries')
            ->whereNull('deleted_at')
            ->where('status', 'published')
            ->count();

        if ($publishable === 0) {
            $this->command?->warn(
                'SearchIndexSeeder: no published entries — run the content seeders first.'
            );

            return;
        }

        // ReindexSearchAction clears the table itself before rebuilding, so the
        // index can never accumulate stale rows across runs.
        $stats = $this->reindex->execute();

        $this->command?->info(
            "Search index rebuilt: {$stats['indexed']} rows from {$stats['total']} published entries"
            .($stats['skipped'] > 0 ? " ({$stats['skipped']} skipped)" : '')
        );

        $perProject = DB::table('search_indices')
            ->join('projects', 'projects.id', '=', 'search_indices.project_id')
            ->groupBy('projects.slug')
            // `rows` is a reserved word in MySQL 8 — quoting it is not worth it
            // when a plain alias reads the same.
            ->selectRaw('projects.slug, COUNT(*) as indexed_rows, COUNT(DISTINCT search_indices.language) as languages')
            ->orderBy('projects.slug')
            ->get();

        if ($perProject->isNotEmpty()) {
            $this->command?->table(
                ['Project', 'Indexed rows', 'Languages'],
                $perProject->map(fn ($r) => [$r->slug, $r->indexed_rows, $r->languages])->all()
            );
        }

        $orphans = DB::table('search_indices')
            ->leftJoin('data_entries', 'data_entries.id', '=', 'search_indices.entry_id')
            ->whereNull('data_entries.id')
            ->count();

        if ($orphans > 0) {
            $this->command?->error(
                "SearchIndexSeeder: {$orphans} indexed rows point at a missing entry."
            );
        }
    }
}
