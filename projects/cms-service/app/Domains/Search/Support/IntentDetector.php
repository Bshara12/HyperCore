<?php

namespace App\Domains\Search\Support;

class IntentDetector
{
    /**
     * الحد الأدنى للـ confidence لاعتبار النية محددة
     * إذا كانت أقل → general
     */
    private const CONFIDENCE_THRESHOLD = 0.3;

    /**
     * خريطة الكلمات الدالة لكل نية
     * الأوزان:
     *   2.0 = مؤشر قوي جداً  (buy, price, حجز)
     *   1.5 = مؤشر قوي       (shop, deal, tutorial)
     *   1.0 = مؤشر متوسط     (review, guide, fix)
     *   0.5 = مؤشر ضعيف      (new, best, top)
     *
     * @var array<string, array<string, float>>
     */
    private const INTENT_SIGNALS = [

        'product' => [
            // ─── English - Strong ────────────────────────────────────
            'buy'        => 2.0,
            'purchase'   => 2.0,
            'price'      => 2.0,
            'cost'       => 1.5,
            'cheap'      => 1.5,
            'expensive'  => 1.5,
            'afford'     => 1.5,
            'affordable' => 1.5,
            'order'      => 1.5,
            'shop'       => 1.5,
            'store'      => 1.5,
            'sell'       => 1.5,
            'sale'       => 1.5,
            'deal'       => 1.5,
            'offer'      => 1.5,
            'discount'   => 1.5,
            'shipping'   => 2.0,
            'delivery'   => 2.0,
            'stock'      => 1.0,
            'brand'      => 1.0,
            'model'      => 1.0,
            'specs'      => 1.0,
            'review'     => 1.0,
            'rating'     => 1.0,
            'compare'    => 1.0,
            'vs'         => 0.5,
            'best'       => 0.5,
            'top'        => 0.5,
            'new'        => 0.5,
            // ─── Arabic - Strong ─────────────────────────────────────
            'شراء'       => 2.0,
            'اشتري'      => 2.0,
            'سعر'        => 2.0,
            'أسعار'      => 2.0,
            'ثمن'        => 2.0,
            'تكلفة'      => 1.5,
            'رخيص'       => 1.5,
            'غالي'       => 1.5,
            'عرض'        => 1.5,
            'خصم'        => 1.5,
            'تخفيض'      => 1.5,
            'توصيل'      => 2.0,
            'شحن'        => 2.0,
            'طلب'        => 1.5,
            'متجر'       => 1.5,
            'بيع'        => 1.5,
            'منتج'       => 1.5,
            'مواصفات'    => 1.0,
            'مقارنة'     => 1.0,
            'تقييم'      => 1.0,
            'أفضل'       => 0.5,
            'جديد'       => 0.5,
        ],

        'article' => [
            // ─── English ─────────────────────────────────────────────
            'how'          => 2.0,
            'what'         => 1.5,
            'why'          => 1.5,
            'when'         => 1.0,
            'tutorial'     => 2.0,
            'guide'        => 2.0,
            'learn'        => 2.0,
            'course'       => 1.5,
            'explain'      => 1.5,
            'introduction' => 1.5,
            'overview'     => 1.5,
            'meaning'      => 1.5,
            'definition'   => 1.5,
            'example'      => 1.5,
            'examples'     => 1.5,
            'step'         => 1.0,
            'steps'        => 1.0,
            'tips'         => 1.0,
            'tricks'       => 1.0,
            'article'      => 1.0,
            'blog'         => 1.0,
            'post'         => 0.5,
            'read'         => 0.5,
            'news'         => 1.0,
            'update'       => 0.5,
            // ─── Arabic ──────────────────────────────────────────────
            'كيف'          => 2.0,
            'ماذا'         => 1.5,
            'لماذا'        => 1.5,
            'شرح'          => 2.0,
            'دليل'         => 2.0,
            'تعلم'         => 2.0,
            'تعليم'        => 2.0,
            'كورس'         => 1.5,
            'مقدمة'        => 1.5,
            'مفهوم'        => 1.5,
            'تعريف'        => 1.5,
            'مثال'         => 1.5,
            'أمثلة'        => 1.5,
            'خطوات'        => 1.0,
            'نصائح'        => 1.0,
            'مقال'         => 1.0,
            'مدونة'        => 1.0,
            'اقرأ'         => 0.5,
            'أخبار'        => 1.0,
            'تحديث'        => 0.5,
        ],

        'service' => [
            // ─── English ─────────────────────────────────────────────
            'repair'      => 2.0,
            'fix'         => 2.0,
            'service'     => 2.0,
            'maintenance' => 2.0,
            'support'     => 1.5,
            'help'        => 1.5,
            'book'        => 2.0,
            'booking'     => 2.0,
            'reserve'     => 2.0,
            'reservation' => 2.0,
            'appointment' => 2.0,
            'schedule'    => 1.5,
            'hire'        => 1.5,
            'rent'        => 1.5,
            'rental'      => 1.5,
            'install'     => 1.5,
            'installation'=> 1.5,
            'setup'       => 1.5,
            'consult'     => 1.5,
            'consultation'=> 1.5,
            'doctor'      => 1.5,
            'clinic'      => 1.5,
            'hospital'    => 1.5,
            'hotel'       => 1.5,
            'flight'      => 1.5,
            'ticket'      => 1.5,
            'delivery'    => 1.0,
            // ─── Arabic ──────────────────────────────────────────────
            'إصلاح'       => 2.0,
            'تصليح'       => 2.0,
            'صيانة'       => 2.0,
            'خدمة'        => 2.0,
            'خدمات'       => 2.0,
            'دعم'         => 1.5,
            'مساعدة'      => 1.5,
            'حجز'         => 2.0,
            'احجز'        => 2.0,
            'موعد'        => 2.0,
            'مواعيد'      => 2.0,
            'استئجار'     => 1.5,
            'إيجار'       => 1.5,
            'تركيب'       => 1.5,
            'تنصيب'       => 1.5,
            'استشارة'     => 1.5,
            'طبيب'        => 1.5,
            'عيادة'       => 1.5,
            'مستشفى'      => 1.5,
            'فندق'        => 1.5,
            'تذكرة'       => 1.5,
            'رحلة'        => 1.0,
        ],
    ];

    // ─────────────────────────────────────────────────────────────────

    /**
     * تحليل كلمات البحث وإرجاع النية المكتشفة
     *
     * @param  string[] $cleanWords
     * @return array{intent: string, confidence: float, scores: array}
     */
    public function detect(array $cleanWords): array
    {
        if (empty($cleanWords)) {
            return $this->buildResult('general', 0.0, []);
        }

        // ─── 1. احسب score لكل نية ────────────────────────────────
        $rawScores = $this->calculateScores($cleanWords);

        // ─── 2. إذا كل الـ scores صفر → general ──────────────────
        $totalScore = array_sum($rawScores);

        if ($totalScore === 0.0) {
            return $this->buildResult('general', 0.0, $rawScores);
        }

        // ─── 3. حوّل إلى نسب مئوية ───────────────────────────────
        $normalizedScores = $this->normalizeScores($rawScores, $totalScore);

        // ─── 4. الفائز = أعلى score ──────────────────────────────
        arsort($normalizedScores);
        $winnerIntent     = array_key_first($normalizedScores);
        $winnerConfidence = $normalizedScores[$winnerIntent];

        // ─── 5. إذا الـ confidence منخفض جداً → general ──────────
        if ($winnerConfidence < self::CONFIDENCE_THRESHOLD) {
            return $this->buildResult('general', $winnerConfidence, $normalizedScores);
        }

        return $this->buildResult($winnerIntent, $winnerConfidence, $normalizedScores);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * حساب الـ score الخام لكل نية
     *
     * @param  string[] $cleanWords
     * @return array<string, float>
     */
    private function calculateScores(array $cleanWords): array
    {
        $scores = [
            'product' => 0.0,
            'article' => 0.0,
            'service' => 0.0,
        ];

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

    /**
     * تحويل الـ raw scores إلى نسب (0.0 → 1.0)
     *
     * @param  array<string, float> $scores
     * @return array<string, float>
     */
    private function normalizeScores(array $scores, float $total): array
    {
        $normalized = [];

        foreach ($scores as $intent => $score) {
            $normalized[$intent] = round($score / $total, 4);
        }

        return $normalized;
    }

    /**
     * بناء الـ result array بشكل موحد
     *
     * @param  array<string, float> $scores
     */
    private function buildResult(
        string $intent,
        float  $confidence,
        array  $scores
    ): array {
        return [
            'intent'     => $intent,
            'confidence' => round($confidence, 4),
            'scores'     => $scores,
        ];
    }
}