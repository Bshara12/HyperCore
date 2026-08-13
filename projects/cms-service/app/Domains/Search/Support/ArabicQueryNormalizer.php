<?php

namespace App\Domains\Search\Support;

class ArabicQueryNormalizer
{
    use NegationExtractionTrait;

    private const NEGATION_PATTERNS = [
        'لا اريد ان'  => 3, 'لا أريد ان'  => 3,
        'لا ابغى ان'  => 3, 'لا أبغى ان'  => 3,
        'مش عايزة ان' => 3,
        'ما بدي'      => 2, 'ما اريد'     => 2,
        'ما أريد'     => 2, 'ما ابغى'     => 2,
        'ما أبغى'     => 2, 'لا اريد'     => 2,
        'لا أريد'     => 2, 'لا ابغى'     => 2,
        'لا أبغى'     => 2, 'مش عايز'     => 2,
        'مش عايزة'    => 2, 'مو بادي'     => 2,
        'مو عايز'     => 2,
        'بدون' => 1, 'بدوني' => 1, 'غير'   => 1,
        'ماعدا'=> 1, 'سوى'   => 1, 'عدا'   => 1,
        'إلا'  => 1, 'الا'   => 1, 'مبغاش' => 1,
        'مابغاش' => 1, 'without' => 1, 'except' => 1,
    ];

    private const FILLER_WORDS = [
        'بدي','ودي','ابي','أبي','نفسي','محتاج','محتاجة',
        'حابب','حابة','عايز','عايزة','ابغى','أبغى',
        'اريد','أريد','ابغاه','ابيه','بغيت','عندي',
        'يا','هلا','ممكن','لو','فيه','وين',
        'want','need','looking','find','show',
        'please','give','tell','help','get',
    ];

    private const TEMPORAL_INDICATORS = [
        'يلي','لحد','الان','الآن','نزل','نزلت','صدر','صدرت',
        'جديد','جديدة','اخر','آخر','قبل','بعد','كل','جميع',
        'بال','سنة','عام','من',
    ];

    private const AR_TO_EN_MAP = [
        'ايفون'   => 'iphone',    'آيفون'   => 'iphone',
        'أيفون'   => 'iphone',    'سامسونج' => 'samsung',
        'سامسونغ' => 'samsung',   'لابتوب'  => 'laptop',
        'جوال'    => 'phone',     'هاتف'    => 'phone',
        'موبايل'  => 'mobile',    'تابلت'   => 'tablet',
        'شاشة'    => 'screen',    'كاميرا'  => 'camera',
        'سعر'     => 'price',     'شراء'    => 'buy',
        'رخيص'    => 'cheap',     'ارخص'    => 'cheap',
        'أرخص'    => 'cheap',     'غالي'    => 'expensive',
        'ساعة'    => 'watch',     'سماعات'  => 'headphones',
        'حاسوب'   => 'computer',  'ماك'     => 'mac',
        'بيكسل'   => 'pixel',     'نوكيا'   => 'nokia',
        'جوجل'    => 'google',    'ابل'     => 'apple',
        'أبل'     => 'apple',     'هواوي'   => 'huawei',
        'شاومي'   => 'xiaomi',    'اوبو'    => 'oppo',
        'تلفزيون' => 'tv',        'تلفاز'   => 'tv',
        'شاحن'    => 'charger',   'بطارية'  => 'battery',
        'كفر'     => 'case',      'غطاء'    => 'cover',
        // intent
        'مونتاج'  => 'video editing',
        'تصوير'   => 'photography',
        'جامعة'   => 'student',
        'برمجة'   => 'programming',
        'العاب'   => 'gaming',
        'الألعاب' => 'gaming',
        'ألعاب'   => 'gaming',
        // product suffixes
        'برو'     => 'pro',
        'ماكس'    => 'max',
        'الترا'   => 'ultra',
        'بلس'     => 'plus',
        'لايت'    => 'lite',
        'ميني'    => 'mini',
        'فيديو'   => 'video',
    ];

    public function normalize(string $query): array
    {
        $normalized = $this->normalizeChars($query);

        [$includeText, $excludeWords, $hadNegation] = $this->extractNegationsBase(
            text:             $normalized,
            negationPatterns: self::NEGATION_PATTERNS,
            splitWords:       fn(string $t) => $this->splitWords($t),
            wordBoundaryCheck: null
        );

        $includeWords = $this->splitWords($includeText);
        $fillers      = array_flip(self::FILLER_WORDS);
        $cleanWords   = array_values(array_filter(
            $includeWords, fn($w) => !isset($fillers[$w])
        ));

        $translatedInclude = $this->translateWords($cleanWords);

        $excludeTerms = $this->buildExcludeTermsList(
            rawWords:       $excludeWords,
            fillerWords:    self::FILLER_WORDS,
            translationMap: self::AR_TO_EN_MAP
        );

        // ══════════════════════════════════════════════════════
        // FIX: أي query يحتوي عربي → isNaturalLanguage = true
        // "ايفون برو ماكس" → 3 كلمات → كل الشروط القديمة false
        // الإصلاح: hasArabicChars() يجعل كل Arabic query تذهب للـ AI
        // ══════════════════════════════════════════════════════
        $isNaturalLanguage = $hadNegation
            || $this->hasArabicChars($normalized)  // ← THE FIX
            || $this->isLongQuery($includeWords)
            || $this->containsFillerWords($includeWords, $fillers)
            || $this->containsTemporalIndicators($normalized);

        return [
            'normalized'        => implode(' ', $translatedInclude),
            'excludeTerms'      => $excludeTerms,
            'isNaturalLanguage' => $isNaturalLanguage,
            'cleanWords'        => $translatedInclude,
        ];
    }

    private function hasArabicChars(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
    }

    private function isLongQuery(array $words): bool
    {
        return count($words) >= 4;
    }

    private function containsFillerWords(array $words, array $fillers): bool
    {
        foreach ($words as $w) {
            if (isset($fillers[$w])) return true;
        }
        return false;
    }

    private function containsTemporalIndicators(string $text): bool
    {
        foreach (self::TEMPORAL_INDICATORS as $ind) {
            if (str_contains($text, $ind)) return true;
        }
        return false;
    }

    private function normalizeChars(string $text): string
    {
        $text = str_replace(['أ','إ','آ','ٱ'], 'ا', $text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $text = str_replace('ـ', '', $text);
        return mb_strtolower(trim($text), 'UTF-8');
    }

    private function splitWords(string $text): array
    {
        if (empty(trim($text))) return [];
        $words = preg_split('/[\s,،.]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($words, fn($w) => mb_strlen(trim($w), 'UTF-8') >= 1));
    }

    private function translateWords(array $words): array
    {
        $result = [];
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') < 2) continue;
            $result[] = self::AR_TO_EN_MAP[$word] ?? $word;
        }
        return array_values(array_unique($result));
    }
}