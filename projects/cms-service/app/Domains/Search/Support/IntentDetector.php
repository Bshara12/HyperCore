<?php

namespace App\Domains\Search\Support;

class IntentDetector
{
    private const CONFIDENCE_THRESHOLD = 0.3;

    /*
     * ─── التغيير الرئيسي ──────────────────────────────────────────────
     * أضفنا intents أكثر دقة:
     *   buy     = نية شراء محددة       → products
     *   repair  = نية إصلاح/صيانة      → services
     *   compare = نية مقارنة           → articles + products
     *   learn   = نية تعلم/استفسار     → articles
     *
     * هذا يُحسن الـ ranking لأن:
     *   "iphone repair" → repair intent → services تظهر أولاً
     *   "iphone vs samsung" → compare intent → articles تظهر أولاً
     *   بدلاً من الاعتماد فقط على product/article/service
     */
    private const INTENT_SIGNALS = [

        // ─── Buy Intent ──────────────────────────────────────────────
        'buy' => [
            'buy' => 2.0, 'purchase' => 2.0, 'order' => 2.0,
            'price' => 2.0, 'cost' => 1.5, 'cheap' => 1.5,
            'afford' => 1.5, 'affordable' => 1.5, 'discount' => 1.5,
            'deal' => 1.5, 'offer' => 1.5, 'sale' => 1.5,
            'shipping' => 2.0, 'delivery' => 2.0, 'shop' => 1.5,
            'store' => 1.5, 'stock' => 1.0, 'sell' => 1.5,
            // Arabic
            'شراء' => 2.0, 'اشتري' => 2.0, 'سعر' => 2.0,
            'أسعار' => 2.0, 'ثمن' => 2.0, 'تكلفة' => 1.5,
            'رخيص' => 1.5, 'خصم' => 1.5, 'عرض' => 1.5,
            'توصيل' => 2.0, 'شحن' => 2.0, 'طلب' => 1.5,
        ],

        // ─── Repair/Service Intent ────────────────────────────────────
        'repair' => [
            'repair' => 2.0, 'fix' => 2.0, 'service' => 2.0,
            'maintenance' => 2.0, 'broken' => 1.5, 'damage' => 1.5,
            'replace' => 1.5, 'screen' => 1.0, 'battery' => 1.0,
            'support' => 1.5, 'help' => 1.0, 'book' => 2.0,
            'booking' => 2.0, 'appointment' => 2.0, 'schedule' => 1.5,
            'hire' => 1.5, 'rent' => 1.5, 'install' => 1.5,
            'setup' => 1.5, 'consult' => 1.5, 'doctor' => 1.5,
            'hotel' => 1.5, 'ticket' => 1.5,
            // Arabic
            'إصلاح' => 2.0, 'تصليح' => 2.0, 'صيانة' => 2.0,
            'خدمة' => 2.0, 'خدمات' => 2.0, 'حجز' => 2.0,
            'موعد' => 2.0, 'تركيب' => 1.5, 'استشارة' => 1.5,
        ],

        // ─── Compare Intent ───────────────────────────────────────────
        'compare' => [
            'vs' => 2.0, 'versus' => 2.0, 'compare' => 2.0,
            'comparison' => 2.0, 'difference' => 2.0, 'better' => 1.5,
            'best' => 1.0, 'top' => 1.0, 'alternative' => 1.5,
            'between' => 1.5, 'which' => 1.5, 'choose' => 1.5,
            'pros' => 1.5, 'cons' => 1.5, 'rating' => 1.0,
            'rank' => 1.0, 'ranked' => 1.0,
            // Arabic
            'مقارنة' => 2.0, 'مقارنة بين' => 2.0, 'الفرق' => 2.0,
            'أفضل' => 1.5, 'بين' => 1.5, 'أم' => 1.5,
        ],

        // ─── Learn Intent ─────────────────────────────────────────────
        'learn' => [
            'how' => 2.0, 'what' => 1.5, 'why' => 1.5,
            'tutorial' => 2.0, 'guide' => 2.0, 'learn' => 2.0,
            'explain' => 1.5, 'introduction' => 1.5, 'overview' => 1.5,
            'meaning' => 1.5, 'definition' => 1.5, 'example' => 1.5,
            'tips' => 1.0, 'tricks' => 1.0, 'course' => 1.5,
            'review' => 1.5, 'article' => 1.0, 'blog' => 1.0,
            'news' => 1.0, 'update' => 0.5, 'documentation' => 1.5,
            // Arabic
            'كيف' => 2.0, 'ماذا' => 1.5, 'لماذا' => 1.5,
            'شرح' => 2.0, 'دليل' => 2.0, 'تعلم' => 2.0,
            'مقدمة' => 1.5, 'مفهوم' => 1.5, 'تعريف' => 1.5,
            'مراجعة' => 1.5, 'أخبار' => 1.0,
        ],
    ];

    /*
     * ─── خريطة التوافق مع data_type slugs ────────────────────────────
     * intent → data_types التي يجب boost-ها
     *
     * مهم: عدّل الـ slugs لتطابق slugs مشروعك الفعلية
     */
    public const INTENT_TO_DATA_TYPE_SLUGS = [
        'buy' => ['products', 'product', 'items', 'goods', 'منتجات'],
        'repair' => ['services', 'service', 'booking', 'appointments', 'خدمات'],
        'compare' => ['articles', 'article', 'posts', 'blog', 'products', 'product'],
        'learn' => ['articles', 'article', 'posts', 'blog', 'news', 'مقالات'],
        'general' => [],
    ];

    // ─────────────────────────────────────────────────────────────────

    public function detect(array $cleanWords): array
    {
        if (empty($cleanWords)) {
            return $this->buildResult('general', 0.0, []);
        }

        $rawScores = $this->calculateScores($cleanWords);
        $totalScore = array_sum($rawScores);

        if ($totalScore === 0.0) {
            return $this->buildResult('general', 0.0, $rawScores);
        }

        $normalizedScores = $this->normalizeScores($rawScores, $totalScore);

        arsort($normalizedScores);
        $winnerIntent = array_key_first($normalizedScores);
        $winnerConfidence = $normalizedScores[$winnerIntent];

        if ($winnerConfidence < self::CONFIDENCE_THRESHOLD) {
            return $this->buildResult('general', $winnerConfidence, $normalizedScores);
        }

        return $this->buildResult($winnerIntent, $winnerConfidence, $normalizedScores);
    }

    // ─────────────────────────────────────────────────────────────────

    private function calculateScores(array $cleanWords): array
    {
        $scores = ['buy' => 0.0, 'repair' => 0.0, 'compare' => 0.0, 'learn' => 0.0];

        foreach ($cleanWords as $word) {
            $word = mb_strtolower(trim($word), 'UTF-8');

            foreach (self::INTENT_SIGNALS as $intent => $signals) {
                if (isset($signals[$word])) {
                    $scores[$intent] += $signals[$word];
                }
            }
        }

        return $scores;
    }

    private function normalizeScores(array $scores, float $total): array
    {
        $normalized = [];
        foreach ($scores as $intent => $score) {
            $normalized[$intent] = round($score / $total, 4);
        }

        return $normalized;
    }

    private function buildResult(string $intent, float $confidence, array $scores): array
    {
        return [
            'intent' => $intent,
            'confidence' => round($confidence, 4),
            'scores' => $scores,
        ];
    }
}
