<?php

declare(strict_types=1);

namespace App\Domains\Search\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SynonymExpander
 *
 * Issue #10 Fix: request-level instance cache لمنع repeated Redis roundtrips
 * لنفس synonym map في نفس الـ request.
 *
 * لماذا instance cache وليس static cache؟
 *   - static مع Octane/Swoole: خطر — يبقى بين requests في نفس worker
 *   - static مع long-running CLI: يتراكم indefinitely
 *   - instance cache: يعيش فقط طول حياة الـ object
 *     - PHP-FPM: object ينتهي مع الـ request ✅
 *     - Octane: كل request ينشئ DI container جديد → instance جديد ✅
 *     - Horizon workers: كل job = DI resolution جديد ✅
 *
 * Performance impact:
 *   Before: 4 Redis GET calls per request (worst case: all fallbacks triggered)
 *   After:  1 Redis GET + 3 array lookups (O(1))
 *   Saving: ~3-6ms per request على same-datacenter Redis
 */
final class SynonymExpander
{
    private const CACHE_TTL_SECONDS  = 3600;
    private const MAX_SYNONYMS_PER_WORD = 3;
    private const MIN_CONFIDENCE     = 0.5;

    /**
     * Instance-level cache — آمن مع Octane و Horizon و PHP-FPM.
     * Key: "projectId:language" → synonym map array
     *
     * لا يُشارَك بين requests — يموت مع الـ object.
     */
    private array $instanceCache = [];

    // ─────────────────────────────────────────────────────────────────

    public function expand(array $tokens, int $projectId, string $language): array
    {
        if (empty($tokens)) {
            return ['expanded' => [], 'groups' => [], 'hadExpansion' => false];
        }

        $synonymMap   = $this->loadSynonymMap($projectId, $language);
        $expandedAll  = [];
        $groups       = [];
        $hadExpansion = false;

        foreach ($tokens as $token) {
            $token    = mb_strtolower(trim($token), 'UTF-8');
            $synonyms = $synonymMap[$token] ?? [];
            $group    = [$token];

            foreach ($synonyms as $synonym) {
                if (! in_array($synonym, $tokens, true) && ! in_array($synonym, $group, true)) {
                    $group[]      = $synonym;
                    $hadExpansion = true;
                }
            }

            $groups[$token] = $group;

            foreach ($group as $word) {
                if (! in_array($word, $expandedAll, true)) {
                    $expandedAll[] = $word;
                }
            }
        }

        return ['expanded' => $expandedAll, 'groups' => $groups, 'hadExpansion' => $hadExpansion];
    }

    // ─────────────────────────────────────────────────────────────────
    // loadSynonymMap — two-level cache
    // ─────────────────────────────────────────────────────────────────

    /**
     * Two-level cache:
     *   Level 1: instance cache  O(1) array lookup   — per-request
     *   Level 2: Redis/file cache                     — cross-request (TTL=1h)
     *   Level 3: DB query                             — on cache miss only
     */
    public function loadSynonymMap(int $projectId, string $language): array
    {
        $instanceKey = "{$projectId}:{$language}";

        // ── Level 1: instance cache ────────────────────────────────────
        // أسرع من أي I/O — O(1) array lookup
        if (isset($this->instanceCache[$instanceKey])) {
            return $this->instanceCache[$instanceKey];
        }

        // ── Level 2: Redis/application cache ──────────────────────────
        $cacheKey = "synonym_map:{$projectId}:{$language}";
        $map      = Cache::remember(
            $cacheKey,
            self::CACHE_TTL_SECONDS,
            fn() => $this->buildSynonymMapFromDB($projectId, $language)
        );

        // خزّن في instance cache للاستدعاءات التالية في نفس الـ request
        $this->instanceCache[$instanceKey] = $map;

        return $map;
    }

    // ─────────────────────────────────────────────────────────────────
    // Cache Invalidation
    // ─────────────────────────────────────────────────────────────────

    public function invalidateCache(int $projectId, string $language): void
    {
        Cache::forget("synonym_map:{$projectId}:{$language}");

        // مسح instance cache أيضاً — ضروري إذا نُودي في نفس الـ request
        unset($this->instanceCache["{$projectId}:{$language}"]);

        Log::info('SynonymExpander: cache invalidated', [
            'project_id' => $projectId,
            'language'   => $language,
        ]);
    }

    public function invalidateCacheForProject(int $projectId): void
    {
        foreach (['en', 'ar', 'fr', 'de', 'es'] as $lang) {
            Cache::forget("synonym_map:{$projectId}:{$lang}");
        }

        // مسح كل instance cache entries لهذا الـ project
        foreach (array_keys($this->instanceCache) as $key) {
            if (str_starts_with($key, "{$projectId}:")) {
                unset($this->instanceCache[$key]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // DB Builder — يُستدعى فقط عند cache miss
    // ─────────────────────────────────────────────────────────────────

    private function buildSynonymMapFromDB(int $projectId, string $language): array
    {
        $rows = DB::table('synonym_suggestions')
            ->select('word_a', 'word_b', 'confidence_score')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->where('status', 'approved')
            ->where('confidence_score', '>=', self::MIN_CONFIDENCE)
            ->orderByDesc('confidence_score')
            ->get();

        if ($rows->isEmpty()) {
            Log::info('SynonymExpander: no approved synonyms', [
                'project_id' => $projectId,
                'language'   => $language,
            ]);
            return [];
        }

        $map = [];

        foreach ($rows as $row) {
            $wordA = mb_strtolower(trim($row->word_a), 'UTF-8');
            $wordB = mb_strtolower(trim($row->word_b), 'UTF-8');

            if (! isset($map[$wordA])) $map[$wordA] = [];
            if (! isset($map[$wordB])) $map[$wordB] = [];

            if (! in_array($wordB, $map[$wordA], true) && count($map[$wordA]) < self::MAX_SYNONYMS_PER_WORD) {
                $map[$wordA][] = $wordB;
            }

            if (! in_array($wordA, $map[$wordB], true) && count($map[$wordB]) < self::MAX_SYNONYMS_PER_WORD) {
                $map[$wordB][] = $wordA;
            }
        }

        Log::info('SynonymExpander: map built', [
            'project_id'   => $projectId,
            'language'     => $language,
            'unique_words' => count($map),
        ]);

        return $map;
    }
}