<?php

namespace App\Domains\Search\Support;

class SynonymScorer
{
    /**
     * حساب الـ Confidence Score النهائي للزوج
     *
     * يجمع عدة عوامل:
     *
     * 1. Jaccard Similarity (0.0 → 1.0) × وزن 0.5
     *    → التشابه الأساسي
     *
     * 2. Frequency Bonus × وزن 0.3
     *    → كلمات تظهر كثيراً معاً أكثر موثوقية
     *    → log10(coOccurrence + 1) / log10(maxCoOccurrence + 1)
     *
     * 3. Balance Bonus × وزن 0.2
     *    → كلمتان متقاربتان في التكرار أكثر موثوقية
     *    → min(freqA, freqB) / max(freqA, freqB)
     *    → مثال: freq(500) و freq(480) → balance = 0.96 ✅
     *    → مثال: freq(500) و freq(5)   → balance = 0.01 ❌
     *
     * النتيجة: 0.0 → 1.0
     */
    public function calculate(
        float $jaccardScore,
        int $coOccurrenceCount,
        int $wordACount,
        int $wordBCount,
        int $maxCoOccurrence   // أعلى قيمة co-occurrence في الـ dataset
    ): float {
        if ($jaccardScore <= 0 || $coOccurrenceCount <= 0) {
            return 0.0;
        }

        // ─── 1. Jaccard Component (أساسي) ────────────────────────────
        $jaccardComponent = $jaccardScore * 0.50;

        // ─── 2. Frequency Component (موثوقية الـ co-occurrence) ───────
        $normalizedFreq = $maxCoOccurrence > 0
            ? log10($coOccurrenceCount + 1) / log10($maxCoOccurrence + 1)
            : 0.0;
        $frequencyComponent = $normalizedFreq * 0.30;

        // ─── 3. Balance Component (توازن التكرار) ─────────────────────
        $minFreq = min($wordACount, $wordBCount);
        $maxFreq = max($wordACount, $wordBCount);
        $balanceRatio = $maxFreq > 0 ? $minFreq / $maxFreq : 0.0;
        $balanceComponent = $balanceRatio * 0.20;

        // ─── الـ Score النهائي ────────────────────────────────────────
        $score = $jaccardComponent + $frequencyComponent + $balanceComponent;

        return round(min(1.0, max(0.0, $score)), 6);
    }
}
