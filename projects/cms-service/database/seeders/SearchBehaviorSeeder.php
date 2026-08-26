<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Search history and click behaviour for every seeded project — the signal the
 * search ranking and personalization features read.
 *
 * Replaces three seeders that fought over the same two tables with
 * incompatible assumptions:
 *
 *  - UserSearchLogsSeeder            hardcoded `projectId: 1`
 *  - UserBehaviorSeeder              hardcoded a PROJECT_ID constant
 *  - PersonalizationAffinityTestSeeder  hardcoded PROJECT_ID + data_type_id 1,
 *                                    and wrote its own search_indices rows,
 *                                    which the reindex then wiped
 *
 * Two things make the generated data actually usable, and both were missing:
 *
 *  1. Clicks are drawn from `search_indices`, not from arbitrary entry ids.
 *     UserPreferenceAnalyzer reaches a user's interests through
 *     `user_click_logs.entry_id = search_indices.entry_id` — a click on an
 *     unindexed entry contributes nothing at all.
 *
 *  2. Keywords come from the indexed titles of each project's own content, so a
 *     logged search plausibly returns the entry that was then clicked. Random
 *     keywords produce search analytics that never line up with the catalogue.
 *
 * Must run AFTER SearchIndexSeeder.
 */
class SearchBehaviorSeeder extends Seeder
{
    /** How many demo searchers to spread the behaviour across. */
    private const SEARCHER_COUNT = 8;

    /** Clicks and searches land inside this window — the analyzer's default. */
    private const WINDOW_DAYS = 25;

    private SeedContext $ctx;

    public function __construct()
    {
        $this->ctx = new SeedContext;
    }

    public function run(): void
    {
        $indexedProjects = DB::table('search_indices')
            ->select('project_id')
            ->distinct()
            ->pluck('project_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($indexedProjects === []) {
            $this->command?->warn(
                'SearchBehaviorSeeder: search index is empty — run SearchIndexSeeder first.'
            );

            return;
        }

        $searchers = $this->searchers();

        $summary = [];

        foreach ($indexedProjects as $projectId) {
            $summary[] = $this->seedProject($projectId, $searchers);
        }

        $this->command?->table(
            ['Project', 'Searches', 'Clicks', 'Popular terms', 'Affinity user'],
            $summary
        );
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * Demo searchers, reused across projects so a user has a cross-tenant
     * history the way a real account would.
     *
     * @return array<int, int> user ids
     */
    private function searchers(): array
    {
        $names = [
            'Amira Nasser', 'Jonas Weber', 'Sofia Rossi', 'Daniel Park',
            'Yara Haddad', 'Tom Becker', 'Noor Aziz', 'Elena Petrova',
        ];

        $ids = [];
        $hash = Hash::make('password123');

        for ($i = 0; $i < self::SEARCHER_COUNT; $i++) {
            $ids[] = $this->ctx->userId(
                sprintf('searcher%d@hypercore.test', $i + 1),
                $names[$i] ?? 'Demo Searcher '.($i + 1),
                $hash
            );
        }

        return $ids;
    }

    /**
     * @param  array<int, int>  $searchers
     * @return array<int, string|int>
     */
    private function seedProject(int $projectId, array $searchers): array
    {
        $slug = DB::table('projects')->where('id', $projectId)->value('slug')
            ?? "#{$projectId}";

        // Already seeded for this project — leave it be so re-runs stay cheap
        // and do not multiply a user's history.
        $existing = DB::table('user_search_logs')
            ->where('project_id', $projectId)
            ->count();

        if ($existing > 0) {
            return [$slug, $existing.' (kept)', '—', '—', '—'];
        }

        $indexed = DB::table('search_indices')
            ->where('project_id', $projectId)
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->select('entry_id', 'data_type_id', 'title', 'language')
            ->get();

        if ($indexed->isEmpty()) {
            return [$slug, '0 (no titles indexed)', '—', '—', '—'];
        }

        // The data type the "affinity" persona will favour, so
        // UserPreferenceAnalyzer has one clear preference to detect.
        $typeIds = $indexed->pluck('data_type_id')->unique()->values();
        $affinityTypeId = (int) $typeIds->first();
        $affinityUserId = $searchers[0];

        $searchRows = [];
        $clickPlans = [];

        foreach ($indexed as $row) {

            // One to three searches per indexed record, phrased from its title.
            $repeats = random_int(1, 3);

            for ($r = 0; $r < $repeats; $r++) {

                $isAffinityType = (int) $row->data_type_id === $affinityTypeId;

                // The affinity persona does most of the searching in its
                // favoured type and little elsewhere; everyone else is uniform.
                $userId = $isAffinityType && random_int(1, 100) <= 70
                    ? $affinityUserId
                    : $searchers[random_int(0, count($searchers) - 1)];

                if (! $isAffinityType && $userId === $affinityUserId && random_int(1, 100) <= 80) {
                    $userId = $searchers[random_int(1, count($searchers) - 1)];
                }

                $keyword = $this->keywordFrom((string) $row->title);

                if ($keyword === '') {
                    continue;
                }

                $searchedAt = Carbon::now()->subDays(random_int(0, self::WINDOW_DAYS))
                    ->subMinutes(random_int(0, 1439));

                $sessionId = 'seed-'.substr(md5($userId.$searchedAt->timestamp), 0, 16);

                $resultsCount = random_int(1, 12);

                $searchRows[] = [
                    'user_id' => $userId,
                    'project_id' => $projectId,
                    'keyword' => Str::limit($keyword, 190, ''),
                    'language' => $row->language ?: 'en',
                    'detected_intent' => $this->intentFor($keyword),
                    'intent_confidence' => random_int(620, 970) / 1000,
                    'results_count' => $resultsCount,
                    'session_id' => $sessionId,
                    'searched_at' => $searchedAt,
                ];

                // Not every search ends in a click — a zero-result-style search
                // with no click is what makes the analytics realistic.
                $clickPlans[] = [
                    'clicks' => random_int(0, 100) <= 72 ? 1 : 0,
                    'entry_id' => (int) $row->entry_id,
                    'data_type_id' => (int) $row->data_type_id,
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'searched_at' => $searchedAt,
                ];
            }
        }

        if ($searchRows === []) {
            return [$slug, 0, 0, 0, '—'];
        }

        // Insert the searches, then read their ids back so each click can point
        // at the search it came from (user_click_logs.search_log_id is a FK).
        $insertedIds = $this->insertSearchLogs($projectId, $searchRows);

        $clickCount = $this->insertClickLogs($projectId, $insertedIds, $clickPlans);

        $popular = $this->rollUpPopularSearches($projectId, $searchRows);

        return [$slug, count($searchRows), $clickCount, $popular, $affinityUserId];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, int> ids in the same order as $rows
     */
    private function insertSearchLogs(int $projectId, array $rows): array
    {
        $before = (int) DB::table('user_search_logs')->max('id');

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_search_logs')->insert($chunk);
        }

        // Auto-increment is monotonic within the insert, so ordering by id
        // recovers the original row order without a round trip per row.
        return DB::table('user_search_logs')
            ->where('project_id', $projectId)
            ->where('id', '>', $before)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $searchLogIds
     * @param  array<int, array<string, mixed>>  $plans
     */
    private function insertClickLogs(int $projectId, array $searchLogIds, array $plans): int
    {
        $rows = [];

        foreach ($plans as $i => $plan) {

            if ($plan['clicks'] === 0 || ! isset($searchLogIds[$i])) {
                continue;
            }

            $rows[] = [
                'user_id' => $plan['user_id'],
                'project_id' => $projectId,
                'search_log_id' => $searchLogIds[$i],
                'entry_id' => $plan['entry_id'],
                'data_type_id' => $plan['data_type_id'],
                // Clicks cluster at the top of the result list.
                'result_position' => min(10, max(1, (int) round(abs($this->gaussian()) * 3) + 1)),
                'session_id' => $plan['session_id'],
                // A click happens shortly after the search it belongs to.
                'clicked_at' => Carbon::parse($plan['searched_at'])->addSeconds(random_int(3, 90)),
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('user_click_logs')->insert($chunk);
        }

        return count($rows);
    }

    /**
     * Aggregate the generated searches into popular_searches, the table the
     * "Popular searches" admin page and the trending endpoint read.
     *
     * @param  array<int, array<string, mixed>>  $searchRows
     */
    private function rollUpPopularSearches(int $projectId, array $searchRows): int
    {
        $buckets = [];
        $now = Carbon::now();

        foreach ($searchRows as $row) {

            $normalized = Str::lower(trim((string) $row['keyword']));
            $key = $row['language'].'|'.$normalized;

            $buckets[$key] ??= [
                'keyword' => $row['keyword'],
                'language' => $row['language'],
                'normalized_keyword' => $normalized,
                'count_24h' => 0,
                'count_7d' => 0,
                'count_30d' => 0,
                'count_all_time' => 0,
                'last_searched_at' => null,
            ];

            $searchedAt = Carbon::parse($row['searched_at']);
            $ageDays = $searchedAt->diffInDays($now);

            $buckets[$key]['count_all_time']++;

            if ($ageDays < 1) {
                $buckets[$key]['count_24h']++;
            }
            if ($ageDays < 7) {
                $buckets[$key]['count_7d']++;
            }
            if ($ageDays < 30) {
                $buckets[$key]['count_30d']++;
            }

            if (
                $buckets[$key]['last_searched_at'] === null
                || $searchedAt->greaterThan($buckets[$key]['last_searched_at'])
            ) {
                $buckets[$key]['last_searched_at'] = $searchedAt;
            }
        }

        $rows = [];

        foreach ($buckets as $bucket) {
            $rows[] = [
                'project_id' => $projectId,
                'keyword' => $bucket['keyword'],
                'language' => $bucket['language'],
                'normalized_keyword' => $bucket['normalized_keyword'],
                'count_24h' => $bucket['count_24h'],
                'count_7d' => $bucket['count_7d'],
                'count_30d' => $bucket['count_30d'],
                'count_all_time' => $bucket['count_all_time'],
                'click_count' => 0,
                // Recent activity weighted above lifetime volume.
                'trending_score' => round(
                    $bucket['count_24h'] * 3 + $bucket['count_7d'] * 1.5 + $bucket['count_30d'] * 0.5,
                    4
                ),
                'alltime_score' => $bucket['count_all_time'],
                'last_searched_at' => $bucket['last_searched_at'],
                'last_computed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            // (project_id, normalized_keyword, language) is unique.
            DB::table('popular_searches')->insertOrIgnore($chunk);
        }

        return count($rows);
    }

    // ─────────────────────────────────────────────────────────────────────

    /**
     * Turn an indexed title into something a person would plausibly type:
     * either the whole title, or a two-to-three word fragment of it.
     */
    private function keywordFrom(string $title): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags($title)) ?? '');

        if ($clean === '') {
            return '';
        }

        if (random_int(1, 100) <= 35) {
            return $clean;
        }

        $words = array_values(array_filter(
            preg_split('/\s+/u', $clean) ?: [],
            fn ($w) => mb_strlen($w) > 2
        ));

        if ($words === []) {
            return $clean;
        }

        $take = min(count($words), random_int(2, 3));
        $start = random_int(0, max(0, count($words) - $take));

        return implode(' ', array_slice($words, $start, $take));
    }

    /**
     * Mirrors the intent labels the search admin pages render
     * (transactional / informational / service).
     */
    private function intentFor(string $keyword): string
    {
        $lower = Str::lower($keyword);

        $transactional = ['buy', 'price', 'order', 'book', 'package', 'شراء', 'سعر', 'حجز'];
        $service = ['consultation', 'visit', 'appointment', 'screening', 'follow', 'استشارة', 'زيارة', 'فحص'];

        foreach ($transactional as $needle) {
            if (Str::contains($lower, $needle)) {
                return 'transactional';
            }
        }

        foreach ($service as $needle) {
            if (Str::contains($lower, $needle)) {
                return 'service';
            }
        }

        return 'informational';
    }

    /**
     * Box–Muller normal sample, so click positions cluster near the top of the
     * result list instead of spreading uniformly.
     */
    private function gaussian(): float
    {
        $u1 = (random_int(1, 1_000_000) / 1_000_000);
        $u2 = (random_int(1, 1_000_000) / 1_000_000);

        return sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
    }
}
