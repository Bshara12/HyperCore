<?php

declare(strict_types=1);

namespace App\Domains\Search\Support;

use App\Domains\Search\DTOs\UserPreferenceDTO;

/**
 * SearchResultRanker
 *
 * Issue #8 Fix — pre-compute expensive operations outside the hot loop:
 *
 *   Before: mb_strtolower() called ~1800× per request (inside nested loops)
 *   After:  mb_strtolower() called ~216× per request (88% reduction)
 *
 * Three sources of redundant lowercasing eliminated:
 *   1. cleanWords:    500× → 5×   (pre-lower before rows loop)
 *   2. firstWord:     100× → 1×   (pre-lower before rows loop)
 *   3. userKeywords: 1000× → 10×  (pre-lower before rows loop)
 *
 * str_contains calls NOT optimized — they are O(n) and necessary.
 * Scoring weights and behavior: UNCHANGED.
 */
final class SearchResultRanker
{
    private const INTENT_SLUGS = [
        'product' => ['products', 'product', 'items', 'goods'],
        'article' => ['articles', 'article', 'posts', 'blog', 'news'],
        'service' => ['services', 'service', 'booking', 'appointments'],
        'buy'     => ['products', 'product', 'items', 'goods'],
        'repair'  => ['services', 'service', 'booking', 'appointments'],
        'learn'   => ['articles', 'article', 'posts', 'blog', 'news'],
        'compare' => ['articles', 'article', 'posts', 'blog', 'products', 'product'],
    ];

    // ─────────────────────────────────────────────────────────────────

    public function rerank(
        array             $rows,
        array             $cleanWords,
        string            $phraseQuery,
        string            $intent,
        float             $intentConf,
        UserPreferenceDTO $preference,
        array             $userKeywords = []
    ): array {
        if (empty($rows)) {
            return [];
        }

        // ══════════════════════════════════════════════════════════════
        // PRE-COMPUTE — خارج loop الـ rows (Issue #8 Fix)
        // ══════════════════════════════════════════════════════════════

        // 1. phraseQueryLower — كان موجوداً بالفعل، نحافظ عليه
        $phraseQueryLower = mb_strtolower($phraseQuery, 'UTF-8');

        // 2. cleanWords lowercased — كان يُحسب داخل loop (500× → 5×)
        $cleanWordsLower = array_map(
            fn(string $w): string => mb_strtolower($w, 'UTF-8'),
            $cleanWords
        );

        // 3. numberWords كـ strings — كان يُحسب جزئياً داخل loop
        $numberWordsStr = array_map(
            'strval',
            array_filter($cleanWords, fn($w) => is_numeric($w))
        );

        // 4. firstWord lowercased — كان يُحسب داخل loop (100× → 1×)
        $firstWordLower = ! empty($cleanWordsLower[0])
            ? $cleanWordsLower[0]
            : null;

        // 5. userKeywords lowercased — أعلى تكلفة (1000× → 10×)
        // كان: mb_strtolower($kw['word']) داخل nested loop على كل row
        $userKeywordsLower = array_map(
            fn(array $kw): array => [
                'word'   => mb_strtolower($kw['word'], 'UTF-8'),
                'weight' => $kw['weight'],
            ],
            $userKeywords
        );

        // 6. intent/preference slugs — invariant، pre-compute مرة واحدة
        $intentSlugs = $this->getSlugs($intent, $intentConf);
        $prefSlugs   = $this->getPreferenceSlugs($preference);

        // ══════════════════════════════════════════════════════════════
        // HOT LOOP — الآن بدون أي mb_strtolower داخله
        // ══════════════════════════════════════════════════════════════

        foreach ($rows as $row) {
            $row->final_score = $this->computeScore(
                row:               $row,
                cleanWordsLower:   $cleanWordsLower,
                phraseQueryLower:  $phraseQueryLower,
                numberWordsStr:    $numberWordsStr,
                firstWordLower:    $firstWordLower,
                intentSlugs:       $intentSlugs,
                intentConf:        $intentConf,
                prefSlugs:         $prefSlugs,
                prefConf:          $preference->confidence,
                userKeywordsLower: $userKeywordsLower,
            );
        }

        usort($rows, fn($a, $b): int => $b->final_score <=> $a->final_score);

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────
    // Score Computation — hot path
    // ─────────────────────────────────────────────────────────────────

    private function computeScore(
        object  $row,
        array   $cleanWordsLower,   // pre-lowered ✅
        string  $phraseQueryLower,  // pre-lowered ✅
        array   $numberWordsStr,    // pre-cast ✅
        ?string $firstWordLower,    // pre-lowered ✅
        array   $intentSlugs,       // pre-computed ✅
        float   $intentConf,
        array   $prefSlugs,         // pre-computed ✅
        float   $prefConf,
        array   $userKeywordsLower, // pre-lowered ✅
    ): float {
        // title وcontent تُخفَّض هنا — ضروري (per-row data)
        $title   = mb_strtolower($row->title   ?? '', 'UTF-8');
        $content = mb_strtolower($row->content ?? '', 'UTF-8');
        $slug    = $row->data_type_slug ?? '';

        // ── A. FULLTEXT Base ──────────────────────────────────────────
        $score = (float) ($row->fulltext_score ?? 0) * 3.0;

        // ── B. Phrase Match ───────────────────────────────────────────
        if ($phraseQueryLower !== '') {
            if (str_contains($title, $phraseQueryLower)) {
                $score += 8.0;
            } elseif (str_contains($content, $phraseQueryLower)) {
                $score += 3.0;
            }
        }

        // ── C. All Terms Presence ─────────────────────────────────────
        // cleanWordsLower: pre-lowered — لا mb_strtolower هنا
        foreach ($cleanWordsLower as $word) {
            if (str_contains($title, $word)) {
                $score += 2.0;
            } elseif (str_contains($content, $word)) {
                $score += 0.5;
            }
        }

        // ── D. Number Token Boost ─────────────────────────────────────
        // numberWordsStr: pre-cast — لا casting هنا
        foreach ($numberWordsStr as $num) {
            if (str_contains($title, $num)) {
                $score += 5.0;
            } else {
                $score -= 1.0;
            }
        }

        // ── E. Position Boost ─────────────────────────────────────────
        // firstWordLower: pre-lowered — لا mb_strtolower هنا
        if ($firstWordLower !== null) {
            $pos = mb_strpos($title, $firstWordLower, 0, 'UTF-8');
            if ($pos !== false) {
                $score += 1.5 / ($pos + 2);
            }
        }

        // ── F. DB Signals ─────────────────────────────────────────────
        $clickCount      = max(0, (int)   ($row->click_count      ?? 0));
        $viewCount       = max(0, (int)   ($row->view_count       ?? 0));
        $popularityScore = max(0, (float) ($row->popularity_score ?? 0));
        $ctrScore        = max(0, (float) ($row->ctr_score        ?? 0));
        $freshnessScore  = max(0, (float) ($row->freshness_score  ?? 0));

        $score += log($clickCount + 1) * 2.5;
        $score += log($viewCount  + 1) * 1.5;
        $score += $popularityScore     * 3.0;
        $score += $ctrScore            * 4.0;
        $score += $freshnessScore      * 2.0;

        // ── G. Intent Boost ───────────────────────────────────────────
        if (! empty($intentSlugs) && in_array($slug, $intentSlugs, true)) {
            $score += $intentConf * 5.0;
        }

        // ── H. Preference Boost ───────────────────────────────────────
        if (! empty($prefSlugs) && in_array($slug, $prefSlugs, true)) {
            $score += $prefConf * 1.5;
        }

        // ── I. User History Boost ─────────────────────────────────────
        // userKeywordsLower: pre-lowered — لا mb_strtolower هنا
        foreach ($userKeywordsLower as $kw) {
            if (str_contains($title, $kw['word'])) {
                $score += $kw['weight'] * 2.0;
                break; // أول match يكفي
            }
        }

        return round($score, 4);
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function getSlugs(string $intent, float $confidence): array
    {
        if ($confidence < 0.3) {
            return [];
        }
        return self::INTENT_SLUGS[$intent] ?? [];
    }

    private function getPreferenceSlugs(UserPreferenceDTO $preference): array
    {
        if (! $preference->hasHistory || $preference->preferredType === 'general') {
            return [];
        }
        return self::INTENT_SLUGS[$preference->preferredType] ?? [];
    }
}