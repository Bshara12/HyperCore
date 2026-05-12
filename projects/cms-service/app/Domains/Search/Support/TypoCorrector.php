<?php

namespace App\Domains\Search\Support;

/**
 * TypoCorrector
 *
 * يُصحّح الأخطاء الإملائية في الكلمات الإنجليزية بدون API.
 *
 * يعمل بطريقتين:
 *   1. قاموس مباشر للأخطاء الشائعة (O(1) lookup)
 *   2. Levenshtein distance على قاموس المنتجات المعروفة
 *
 * يُستخدم كـ Step مستقل في pipeline بدون الحاجة لـ AI.
 */
class TypoCorrector
{
    // ─── قاموس الأخطاء المباشر ───────────────────────────────────────
    private const TYPO_MAP = [
        // Apple
        'iphoen'   => 'iphone',  'ipone'    => 'iphone',
        'iphon'    => 'iphone',  'iphne'    => 'iphone',
        'ifone'    => 'iphone',  'iphonr'   => 'iphone',
        'iohone'   => 'iphone',  'iphoe'    => 'iphone',
        'iphoone'  => 'iphone',  'iphobe'   => 'iphone',
        'aipple'   => 'apple',   'aplpe'    => 'apple',
        'appel'    => 'apple',   'aplle'    => 'apple',
        'macbok'   => 'macbook', 'makbook'  => 'macbook',
        'macboo'   => 'macbook', 'macbbok'  => 'macbook',
        'airpods'  => 'airpods', 'airpod'   => 'airpods',
        // Samsung
        'samsng'   => 'samsung', 'samsong'  => 'samsung',
        'sasmung'  => 'samsung', 'smasung'  => 'samsung',
        'samsnug'  => 'samsung', 'samsumg'  => 'samsung',
        'samsug'   => 'samsung', 'samsun'   => 'samsung',
        'galxy'    => 'galaxy',  'gallaxy'  => 'galaxy',
        'galaxi'   => 'galaxy',  'glaxy'    => 'galaxy',
        // Google
        'googel'   => 'google',  'gogle'    => 'google',
        'gooogle'  => 'google',  'googl'    => 'google',
        'pixle'    => 'pixel',   'pxiel'    => 'pixel',
        'pixal'    => 'pixel',   'pxel'     => 'pixel',
        // Devices
        'laptp'    => 'laptop',  'labtop'   => 'laptop',
        'leptop'   => 'laptop',  'laptob'   => 'laptop',
        'latpop'   => 'laptop',  'lpatop'   => 'laptop',
        'laptoop'  => 'laptop',  'laotop'   => 'laptop',
        'tabelt'   => 'tablet',  'tabet'    => 'tablet',
        'tablat'   => 'tablet',  'tabler'   => 'tablet',
        'tablt'    => 'tablet',  'tablte'   => 'tablet',
        'headfone' => 'headphone','hedphone' => 'headphone',
        'earphon'  => 'earphone','erafone'  => 'earphone',
        'phoen'    => 'phone',   'fone'     => 'phone',
        'phon'     => 'phone',   'pone'     => 'phone',
        // Brands
        'nokea'    => 'nokia',   'nokiya'   => 'nokia',
        'nokai'    => 'nokia',   'nkia'     => 'nokia',
        'huaewi'   => 'huawei',  'hawuei'   => 'huawei',
        'huawai'   => 'huawei',  'hauwei'   => 'huawei',
        'xiomi'    => 'xiaomi',  'xaomi'    => 'xiaomi',
        'shaomi'   => 'xiaomi',  'xiaommi'  => 'xiaomi',
        // Common words
        'cheep'    => 'cheap',   'chep'     => 'cheap',
        'prie'     => 'price',   'prcie'    => 'price',
        'rpice'    => 'price',   'prise'    => 'price',
        'wireles'  => 'wireless','bluetoth' => 'bluetooth',
        'chager'   => 'charger', 'baterry'  => 'battery',
        'batery'   => 'battery', 'camra'    => 'camera',
        'camear'   => 'camera',  'screeen'  => 'screen',
        'androd'   => 'android', 'androied' => 'android',
    ];

    // ─── قاموس الكلمات الصحيحة للـ Levenshtein ───────────────────────
    // نبحث بين هذه الكلمات فقط لتجنب false positives
    private const KNOWN_WORDS = [
        'iphone', 'samsung', 'apple', 'google', 'pixel', 'galaxy',
        'laptop', 'macbook', 'tablet', 'android', 'huawei', 'xiaomi',
        'nokia', 'oppo', 'phone', 'mobile', 'screen', 'camera',
        'battery', 'charger', 'wireless', 'bluetooth', 'headphone',
        'earphone', 'watch', 'airpods', 'computer', 'keyboard',
        'monitor', 'printer', 'router', 'speaker', 'cheap', 'price',
    ];

    // ─────────────────────────────────────────────────────────────────

    /**
     * تصحيح الـ query — يُعيد الكلمة المصحَّحة أو null
     *
     * @return array{
     *   corrected: string|null,
     *   original: string,
     *   hadCorrection: bool,
     *   confidence: float
     * }
     */
    public function correct(string $query): array
    {
        $query   = trim($query);
        $lower   = mb_strtolower($query, 'UTF-8');
        $words   = preg_split('/\s+/u', $lower, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return $this->buildResult(null, $query, false, 0.0);
        }

        $correctedWords = [];
        $hadAnyFix      = false;
        $totalConf      = 0.0;

        foreach ($words as $word) {
            [$fixed, $conf] = $this->correctWord($word);

            if ($fixed !== null && $fixed !== $word) {
                $correctedWords[] = $fixed;
                $hadAnyFix        = true;
                $totalConf       += $conf;
            } else {
                $correctedWords[] = $word;
                $totalConf       += 1.0; // الكلمات الصحيحة confidence=1
            }
        }

        if (! $hadAnyFix) {
            return $this->buildResult(null, $query, false, 0.0);
        }

        $avgConf   = $totalConf / count($words);
        $corrected = implode(' ', $correctedWords);

        return $this->buildResult($corrected, $query, true, round($avgConf, 4));
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * تصحيح كلمة واحدة
     *
     * @return array{0: string|null, 1: float}  [corrected, confidence]
     */
    private function correctWord(string $word): array
    {
        // 1. قاموس مباشر O(1)
        if (isset(self::TYPO_MAP[$word])) {
            return [self::TYPO_MAP[$word], 0.95];
        }

        // 2. إذا الكلمة موجودة في KNOWN_WORDS → صحيحة
        if (in_array($word, self::KNOWN_WORDS, true)) {
            return [$word, 1.0];
        }

        // 3. Levenshtein على KNOWN_WORDS فقط
        // نتجاهل كلمات قصيرة جداً (< 4 حروف) لتجنب false positives
        $wordLen = mb_strlen($word, 'UTF-8');
        if ($wordLen < 4) {
            return [null, 0.0];
        }

        $bestWord = null;
        $bestDist = PHP_INT_MAX;
        // نسمح بمسافة أقصاها: 1 لكلمات <= 5، 2 لكلمات <= 8، 3 لأطول
        $maxDist  = match (true) {
            $wordLen <= 5 => 1,
            $wordLen <= 8 => 2,
            default       => 3,
        };

        foreach (self::KNOWN_WORDS as $known) {
            // تجاهل إذا الفرق في الطول أكبر من maxDist
            if (abs(mb_strlen($known, 'UTF-8') - $wordLen) > $maxDist) {
                continue;
            }

            $dist = levenshtein($word, $known);

            if ($dist > 0 && $dist <= $maxDist && $dist < $bestDist) {
                $bestDist = $dist;
                $bestWord = $known;
            }
        }

        if ($bestWord === null) {
            return [null, 0.0];
        }

        // confidence أعلى كلما كانت المسافة أقل
        $confidence = match ($bestDist) {
            1 => 0.88,
            2 => 0.72,
            3 => 0.55,
            default => 0.40,
        };

        return [$bestWord, $confidence];
    }

    // ─────────────────────────────────────────────────────────────────

    private function buildResult(?string $corrected, string $original, bool $hadCorrection, float $confidence): array
    {
        return [
            'corrected'     => $corrected,
            'original'      => $original,
            'hadCorrection' => $hadCorrection,
            'confidence'    => $confidence,
        ];
    }
}