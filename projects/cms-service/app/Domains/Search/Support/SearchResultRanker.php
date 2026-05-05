<?php

namespace App\Domains\Search\Support;

use App\Domains\Search\DTOs\UserPreferenceDTO;

class SearchResultRanker
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

        $phraseQueryLower = mb_strtolower($phraseQuery, 'UTF-8');
        $numberWords      = array_filter($cleanWords, fn($w) => is_numeric($w));
        $intentSlugs      = $this->getSlugs($intent, $intentConf);
        $prefSlugs        = $this->getPreferenceSlugs($preference);

        foreach ($rows as $row) {
            $row->final_score = $this->computeScore(
                row:              $row,
                cleanWords:       $cleanWords,
                phraseQueryLower: $phraseQueryLower,
                numberWords:      $numberWords,
                intentSlugs:      $intentSlugs,
                intentConf:       $intentConf,
                prefSlugs:        $prefSlugs,
                prefConf:         $preference->confidence,
                userKeywords:     $userKeywords,
            );
        }

        usort($rows, fn($a, $b) => $b->final_score <=> $a->final_score);

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────

    private function computeScore(
        object $row,
        array  $cleanWords,
        string $phraseQueryLower,
        array  $numberWords,
        array  $intentSlugs,
        float  $intentConf,
        array  $prefSlugs,
        float  $prefConf,
        array  $userKeywords,
    ): float {

        $title   = mb_strtolower($row->title   ?? '', 'UTF-8');
        $content = mb_strtolower($row->content ?? '', 'UTF-8');
        $slug    = $row->data_type_slug ?? '';

        // ─── A. FULLTEXT Base (من DB) ─────────────────────────────────
        $score = (float) ($row->fulltext_score ?? 0) * 3.0;

        // ─── B. Phrase Match ──────────────────────────────────────────
        if (!empty($phraseQueryLower)) {
            if (str_contains($title, $phraseQueryLower)) {
                $score += 8.0;
            } elseif (str_contains($content, $phraseQueryLower)) {
                $score += 3.0;
            }
        }

        // ─── C. All Terms Presence ────────────────────────────────────
        foreach ($cleanWords as $word) {
            $w = mb_strtolower($word, 'UTF-8');
            if (str_contains($title, $w)) {
                $score += 2.0;
            } elseif (str_contains($content, $w)) {
                $score += 0.5;
            }
        }

        // ─── D. Number Token Boost (الأهم لحل مشكلة iphone 15) ───────
        foreach ($numberWords as $num) {
            if (str_contains($title, (string) $num)) {
                $score += 5.0;
            } else {
                $score -= 1.0; // عقوبة: الرقم المطلوب غير موجود
            }
        }

        // ─── E. Position Boost ────────────────────────────────────────
        if (!empty($cleanWords[0])) {
            $firstWord = mb_strtolower($cleanWords[0], 'UTF-8');
            $pos = mb_strpos($title, $firstWord, 0, 'UTF-8');
            if ($pos !== false) {
                $score += 1.5 / ($pos + 2);
            }
        }

        // ─── F. DB Signals ────────────────────────────────────────────
        /*
         * هنا كانت المشكلة: الأعمدة موجودة لكن لم تُستخدم
         * بعد إصلاح view_count tracking، هذه الأعمدة ستحتوي قيماً حقيقية
         */
        $clickCount      = max(0, (int) ($row->click_count   ?? 0));
        $viewCount       = max(0, (int) ($row->view_count    ?? 0));
        $popularityScore = max(0, (float) ($row->popularity_score ?? 0));
        $ctrScore        = max(0, (float) ($row->ctr_score    ?? 0));
        $freshnessScore  = max(0, (float) ($row->freshness_score ?? 0));

        // Click popularity: LOG لتخفيف هيمنة الأرقام الكبيرة
        $score += log($clickCount + 1) * 2.5;

        // View popularity
        $score += log($viewCount + 1) * 1.5;

        // Stored popularity score (مُحسوب بالـ Job)
        $score += $popularityScore * 3.0;

        // CTR Boost: نسبة النقر مهمة جداً
        $score += $ctrScore * 4.0;

        // Freshness
        $score += $freshnessScore * 2.0;

        // ─── G. Intent Boost ─────────────────────────────────────────
        if (!empty($intentSlugs) && in_array($slug, $intentSlugs, true)) {
            $score += $intentConf * 5.0;
        }

        // ─── H. Preference Boost ─────────────────────────────────────
        if (!empty($prefSlugs) && in_array($slug, $prefSlugs, true)) {
            $score += $prefConf * 1.5;
        }

        // ─── I. User History Boost (Time-Decayed) ────────────────────
        foreach ($userKeywords as $kw) {
            $kwLower = mb_strtolower($kw['word'], 'UTF-8');
            if (str_contains($title, $kwLower)) {
                $score += $kw['weight'] * 2.0;
                break;
            }
        }

        return round($score, 4);
    }

    // ─────────────────────────────────────────────────────────────────

    private function getSlugs(string $intent, float $confidence): array
    {
        if ($confidence < 0.3) return [];
        return self::INTENT_SLUGS[$intent] ?? [];
    }

    private function getPreferenceSlugs(UserPreferenceDTO $preference): array
    {
        if (!$preference->hasHistory || $preference->preferredType === 'general') {
            return [];
        }
        return self::INTENT_SLUGS[$preference->preferredType] ?? [];
    }
}