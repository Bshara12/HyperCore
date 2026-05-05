<?php

namespace App\Domains\Search\Support;

class CoOccurrenceAnalyzer
{
    /**
     * بناء مصفوفة الـ Co-occurrence من keywords مُقسَّمة
     *
     * المنطق:
     *   لكل keyword يحتوي على أكثر من كلمة واحدة
     *   نُسجل كل زوج من كلماتها كـ co-occurrence
     *
     * مثال:
     *   keywords = [
     *     ["iphone", "price"],
     *     ["iphone", "cost"],
     *     ["apple", "price"],
     *     ["phone", "price"],
     *   ]
     *
     *   coOccurrence = {
     *     "iphone|price" => 1,
     *     "iphone|cost"  => 1,
     *     "apple|price"  => 1,
     *     "phone|price"  => 1,
     *   }
     *
     *   wordFrequency = {
     *     "iphone" => 2,
     *     "price"  => 3,
     *     "cost"   => 1,
     *     "apple"  => 1,
     *     "phone"  => 1,
     *   }
     *
     * @param  string[][]  $tokenizedKeywords
     * @return array{coOccurrence: array<string,int>, wordFrequency: array<string,int>}
     */
    public function analyze(array $tokenizedKeywords): array
    {
        $coOccurrence = [];  // "wordA|wordB" => count
        $wordFrequency = [];  // "word" => count
        foreach ($tokenizedKeywords as $tokens) {
            if (count($tokens) === 1) {
                echo 'Single token: '.implode(',', $tokens).PHP_EOL;
            } else {
                echo 'Multi token: '.implode(',', $tokens).PHP_EOL;
            }
        }
        foreach ($tokenizedKeywords as $tokens) {

            // ─── count كل كلمة ────────────────────────────────────────
            foreach ($tokens as $token) {
                $wordFrequency[$token] = ($wordFrequency[$token] ?? 0) + 1;
            }

            // ─── co-occurrence لكل زوج في نفس الـ keyword ────────────
            $tokenCount = count($tokens);

            for ($i = 0; $i < $tokenCount; $i++) {
                for ($j = $i + 1; $j < $tokenCount; $j++) {
                    // ترتيب أبجدي لمنع التكرار
                    $pair = $this->buildPairKey($tokens[$i], $tokens[$j]);
                    $coOccurrence[$pair] = ($coOccurrence[$pair] ?? 0) + 1;
                }
            }
        }

        return [
            'coOccurrence' => $coOccurrence,
            'wordFrequency' => $wordFrequency,
        ];
    }

    /**
     * حساب Jaccard Similarity بين زوج من الكلمات
     *
     * الصيغة:
     *   Jaccard(A, B) = |A ∩ B| / |A ∪ B|
     *                = coOccurrence(A,B) / (freq(A) + freq(B) - coOccurrence(A,B))
     *
     * القيم:
     *   0.0 = لا يوجد أي تشابه
     *   1.0 = متطابقان تماماً
     *
     * مثال:
     *   freq(iphone) = 10
     *   freq(mobile) = 8
     *   coOccurrence(iphone, mobile) = 6
     *
     *   Jaccard = 6 / (10 + 8 - 6) = 6/12 = 0.5
     */
    public function jaccardSimilarity(
        string $wordA,
        string $wordB,
        array $coOccurrence,
        array $wordFrequency
    ): float {
        $pairKey = $this->buildPairKey($wordA, $wordB);
        $coCount = $coOccurrence[$pairKey] ?? 0;

        if ($coCount === 0) {
            return 0.0;
        }

        $freqA = $wordFrequency[$wordA] ?? 0;
        $freqB = $wordFrequency[$wordB] ?? 0;

        $union = $freqA + $freqB - $coCount;

        if ($union <= 0) {
            return 0.0;
        }

        return round($coCount / $union, 6);
    }

    /**
     * بناء key للزوج بترتيب أبجدي
     * يضمن أن (php, laravel) و (laravel, php) نفس الـ key
     */
    public function buildPairKey(string $wordA, string $wordB): string
    {
        return strcmp($wordA, $wordB) <= 0
          ? "{$wordA}|{$wordB}"
          : "{$wordB}|{$wordA}";
    }
}
