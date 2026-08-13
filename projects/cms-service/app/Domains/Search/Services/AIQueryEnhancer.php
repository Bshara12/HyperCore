<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\Services\AI\AIProviderInterface;
use App\Domains\Search\Services\AI\GeminiProvider;
use App\Domains\Search\Services\AI\OpenRouterProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIQueryEnhancer
{
    private const CACHE_TTL = 3600;

    private const TYPO_DICTIONARY = [
        'iphoen'=>'iphone','ipone'=>'iphone','iphon'=>'iphone',
        'iphne'=>'iphone','ifone'=>'iphone','samsng'=>'samsung',
        'samsong'=>'samsung','sasmung'=>'samsung','smasung'=>'samsung',
        'samsnug'=>'samsung','samsumg'=>'samsung','googel'=>'google',
        'gogle'=>'google','laptp'=>'laptop','labtop'=>'laptop',
        'leptop'=>'laptop','laptob'=>'laptop','latpop'=>'laptop',
        'lpatop'=>'laptop','macbok'=>'macbook','makbook'=>'macbook',
        'androd'=>'android','androied'=>'android','androdi'=>'android',
        'andriod'=>'android','prie'=>'price','prise'=>'price',
        'prcie'=>'price','rpice'=>'price','cheep'=>'cheap',
        'chep'=>'cheap','chap'=>'cheap','chepa'=>'cheap',
        'phoen'=>'phone','fone'=>'phone','phon'=>'phone',
        'pone'=>'phone','tabelt'=>'tablet','tabet'=>'tablet',
        'tablat'=>'tablet','samsun'=>'samsung','galxy'=>'galaxy',
        'gallaxy'=>'galaxy','galaxi'=>'galaxy','nokea'=>'nokia',
        'nokiya'=>'nokia','pixle'=>'pixel','pxiel'=>'pixel',
        'wireles'=>'wireless','bluetoth'=>'bluetooth',
        'chager'=>'charger','chargr'=>'charger',
        'baterry'=>'battery','batery'=>'battery',
        'camra'=>'camera','camear'=>'camera',
        'screeen'=>'screen','scren'=>'screen',
        'headfone'=>'headphone','earphon'=>'earphone',
        'hedphone'=>'headphone','wirless'=>'wireless',
    ];

    private const AR_PRODUCT_MAP = [
        'ايفون'=>'iphone','آيفون'=>'iphone','أيفون'=>'iphone',
        'سامسونج'=>'samsung','سامسونغ'=>'samsung','لابتوب'=>'laptop',
        'جوال'=>'phone','هاتف'=>'phone','موبايل'=>'mobile',
        'تابلت'=>'tablet','شاشة'=>'screen','كاميرا'=>'camera',
        'سعر'=>'price','شراء'=>'buy','رخيص'=>'cheap',
        'ارخص'=>'cheap','أرخص'=>'cheap','غالي'=>'expensive',
        'ساعة'=>'watch','سماعات'=>'headphones','حاسوب'=>'computer',
        'ماك'=>'mac','بيكسل'=>'pixel','نوكيا'=>'nokia',
        'جوجل'=>'google','ابل'=>'apple','أبل'=>'apple',
        'هواوي'=>'huawei','شاومي'=>'xiaomi','اوبو'=>'oppo',
        'تلفزيون'=>'tv','تلفاز'=>'tv','شاحن'=>'charger',
        'بطارية'=>'battery','كفر'=>'case','غطاء'=>'cover',
        'مونتاج'=>'video editing','تصوير'=>'photography',
        'جامعة'=>'student','برمجة'=>'programming',
        'العاب'=>'gaming','الألعاب'=>'gaming','ألعاب'=>'gaming',
        'برو'=>'pro','ماكس'=>'max','الترا'=>'ultra',
        'بلس'=>'plus','لايت'=>'lite','ميني'=>'mini',
        'فيديو'=>'video',
    ];

    private const AR_NEGATION_PATTERNS = [
        'ما بدي','ما اريد','ما أريد','ما ابغى','ما أبغى',
        'لا اريد','لا أريد','لا ابغى','لا أبغى',
        'مش عايز','مش عايزة','مو بادي','مو عايز',
        'بدون','بدوني','غير','ماعدا','سوى','عدا','إلا',
        'مبغاش','مابغاش',
    ];

    private const AR_FILLER_WORDS = [
        'بدي','ودي','ابي','أبي','نفسي','محتاج','محتاجة',
        'حابب','حابة','عايز','عايزة','ابغى','أبغى',
        'اريد','أريد','ابغاه','ابيه','بغيت','عندي',
        'يا','هلا','ممكن','لو','فيه','وين','كتير',
        'منيح','حلو','شي','حاجة','يلي','لحد','الان',
        'نزل','نزلت','كل',
    ];

    public function __construct(private ?array $providers = null)
    {
        if ($this->providers === null) {
            $this->providers = $this->buildProviderChain();
        }
    }

    // FIX: يقرأ api_key بدل key لـ Gemini
    private function buildProviderChain(): array
    {
        $providers = [];

        if (!empty(config('services.gemini.api_key'))) {
            $providers[] = new GeminiProvider();
        }

        if (!empty(config('services.openrouter.key'))) {
            $providers[] = new OpenRouterProvider();
        }

        if (empty($providers)) {
            Log::warning('AIQueryEnhancer: no providers configured. Check GEMINI_API_KEY and OPENROUTER_API_KEY');
        }

        return $providers;
    }

    public function enhance(string $query, string $language): array
    {
        $query = trim($query);

        if (empty($query)) {
            return $this->emptyResult($query, 0.0, 'empty_input');
        }

        $cacheKey = 'ai_enhance:' . md5(mb_strtolower($query, 'UTF-8') . ':' . $language);
        $cached   = Cache::get($cacheKey);

        if ($cached !== null) {
            Log::debug('AIQueryEnhancer: cache hit', ['query' => $query]);
            return array_merge($cached, ['source' => 'cache']);
        }

        // Gibberish → لا API
        if ($this->isGibberish($query)) {
            $result = $this->emptyResult($query, 0.04, 'gibberish');
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return $result;
        }

        // AI Provider Chain
        $aiResult = $this->tryProviderChain($query, $language);

        if ($aiResult !== null) {
            $result = $this->convertProviderOutput($aiResult, $query);
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return array_merge($result, ['source' => $aiResult['_provider'] ?? 'api']);
        }

        // Local Fallback
        if ($this->isArabic($query)) {
            $result = $this->processArabicLocally($query);
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return array_merge($result, ['source' => 'arabic_local']);
        }

        $result = $this->processEnglishLocally($query);
        Cache::put($cacheKey, $result, self::CACHE_TTL);
        return array_merge($result, ['source' => 'local_fallback']);
    }

    private function tryProviderChain(string $query, string $language): ?array
    {
        foreach ($this->providers as $provider) {
            try {
                $result = $provider->normalize($query, $language);

                if (($result['confidence'] ?? 0.0) < 0.50) {
                    Log::info('AIQueryEnhancer: low confidence, trying next', [
                        'provider'   => $provider->name(),
                        'confidence' => $result['confidence'],
                    ]);
                    continue;
                }

                if (empty(trim($result['normalized_query'] ?? ''))) {
                    continue;
                }

                $result['_provider'] = $provider->name();
                return $result;

            } catch (\Throwable $e) {
                Log::warning("AIQueryEnhancer: provider [{$provider->name()}] failed", [
                    'error' => $e->getMessage(),
                    'query' => $query,
                ]);
                continue;
            }
        }

        return null;
    }

    private function convertProviderOutput(array $providerResult, string $originalQuery): array
    {
        $normalized    = trim($providerResult['normalized_query'] ?? '');
        $confidence    = $providerResult['confidence'] ?? 0.0;
        $negativeTerms = $providerResult['negative_terms'] ?? [];

        $includeTerms = array_values(array_filter(
            explode(' ', $normalized),
            fn($w) => mb_strlen(trim($w), 'UTF-8') >= 2
        ));

        return [
            'correctedQuery'   => $normalized,
            'include'          => $includeTerms,
            'exclude'          => $negativeTerms,
            'expandedKeywords' => [],
            'intent'           => 'general',
            'confidence'       => $confidence,
            'originalQuery'    => $originalQuery,
        ];
    }

    private function processArabicLocally(string $query): array
    {
        $normalized = $this->normalizeArabicChars($query);
        $exclude    = [];
        $cleanText  = $normalized;

        $patterns = self::AR_NEGATION_PATTERNS;
        usort($patterns, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        foreach ($patterns as $pattern) {
            $pos = mb_strpos($cleanText, $pattern, 0, 'UTF-8');
            if ($pos === false) continue;

            $afterText  = trim(mb_substr($cleanText, $pos + mb_strlen($pattern, 'UTF-8'), null, 'UTF-8'));
            $afterWords = $this->splitArabicWords($afterText);

            foreach (array_slice($afterWords, 0, 3) as $word) {
                $word = trim($word);
                if (in_array($word, self::AR_FILLER_WORDS, true)) continue;
                $canonical = $this->stripDefiniteArticle($word);
                $exclude[] = is_numeric($word)
                    ? $word
                    : (self::AR_PRODUCT_MAP[$canonical] ?? self::AR_PRODUCT_MAP[$word] ?? $word);
            }

            $cleanText = trim(mb_substr($cleanText, 0, $pos, 'UTF-8'));
            break;
        }

        $include = [];
        $fillers = array_flip(self::AR_FILLER_WORDS);
        $words   = $this->splitArabicWords($cleanText);

        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') < 2) continue;
            if (isset($fillers[$word])) continue;
            // BUG 2 FIX: canonical normalization قبل البحث في الخريطة
            // "الايفون" → "ايفون" → يطابق AR_PRODUCT_MAP بنجاح
            $canonical  = $this->stripDefiniteArticle($word);
            $translated = self::AR_PRODUCT_MAP[$canonical] ?? self::AR_PRODUCT_MAP[$word] ?? $word;
            if (!empty($translated)) $include[] = $translated;
        }

        $include = array_values(array_unique($include));
        $exclude = array_values(array_unique($exclude));

        if (empty($include) && empty($exclude)) {
            return $this->emptyResult($query, 0.3, 'arabic_empty');
        }

        return [
            'correctedQuery'   => implode(' ', $include),
            'include'          => $include,
            'exclude'          => $exclude,
            'expandedKeywords' => [],
            'intent'           => 'general',
            'confidence'       => !empty($exclude) ? 0.87 : 0.72,
            'originalQuery'    => $query,
        ];
    }

    private function processEnglishLocally(string $query): array
    {
        $rawWords  = preg_split('/\s+/', mb_strtolower(trim($query), 'UTF-8'));
        $corrected = [];
        $hadFix    = false;

        // BUG 1 FIX: نفصل أي token ملتصق حروف+أرقام قبل المعالجة
        // "iphnoe15" → ["iphnoe", "15"] بدل token واحد غير قابل للتصحيح
        $words = [];
        foreach ($rawWords as $raw) {
            foreach ($this->splitAlphaNumeric($raw) as $part) {
                $words[] = $part;
            }
        }

        foreach ($words as $word) {
            $word = preg_replace('/[^a-z0-9]/i', '', $word);
            if (mb_strlen($word) < 2) continue;

            if (isset(self::TYPO_DICTIONARY[$word])) {
                $fixed = self::TYPO_DICTIONARY[$word];
                if ($fixed !== $word) $hadFix = true;
                $corrected[] = $fixed;
                continue;
            }

            $best = $this->levenshteinCorrect($word);
            if ($best !== null && $best !== $word) {
                $hadFix      = true;
                $corrected[] = $best;
            } else {
                $corrected[] = $word;
            }
        }

        $corrected = array_values(array_unique(array_filter($corrected)));

        if (empty($corrected)) {
            return $this->emptyResult($query, 0.2, 'english_empty');
        }

        return [
            'correctedQuery'   => implode(' ', $corrected),
            'include'          => $corrected,
            'exclude'          => [],
            'expandedKeywords' => [],
            'intent'           => 'general',
            'confidence'       => $hadFix ? 0.82 : 0.60,
            'originalQuery'    => $query,
        ];
    }

    /**
     * BUG 1 FIX — Helper
     *
     * يفصل token ملتصق حروف+أرقام إلى أجزاء منفصلة
     * "iphnoe15" → ["iphnoe", "15"]
     * "iphone15pro" → ["iphone", "15", "pro"]
     * "iphone" → ["iphone"]  (بدون تغيير لو ما فيه أرقام)
     */
    private function splitAlphaNumeric(string $word): array
    {
        if (preg_match('/^[a-z]+$/i', $word) || preg_match('/^[0-9]+$/', $word)) {
            return [$word]; // حروف فقط أو أرقام فقط — لا حاجة للفصل
        }

        $parts = preg_split('/(?<=[a-z])(?=[0-9])|(?<=[0-9])(?=[a-z])/i', $word, -1, PREG_SPLIT_NO_EMPTY);

        return $parts ?: [$word];
    }

    private function levenshteinCorrect(string $word): ?string
    {
        $wordLen    = strlen($word);
        $bestMatch  = null;
        $bestDist   = PHP_INT_MAX;
        $maxAllowed = $wordLen <= 5 ? 2 : ($wordLen <= 8 ? 3 : 4);

        foreach (self::TYPO_DICTIONARY as $dictWord => $correction) {
            if (abs(strlen($dictWord) - $wordLen) > $maxAllowed) continue;
            $dist = levenshtein($word, $dictWord);
            if ($dist < $bestDist && $dist <= $maxAllowed) {
                $bestDist  = $dist;
                $bestMatch = $correction;
            }
        }

        return $bestMatch;
    }

    public function isGibberish(string $text): bool
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        if (empty($text) || mb_strlen($text, 'UTF-8') < 4) return false;
        if ($this->isArabic($text)) return false;

        $letters = preg_replace('/[^a-z]/i', '', $text);
        $len     = strlen($letters);
        if ($len < 4) return false;

        $vowels = preg_replace('/[^aeiou]/i', '', $letters);
        if (strlen($vowels) / $len < 0.08) return true;
        if (preg_match('/[^aeiou\s]{6,}/i', $letters)) return true;

        return false;
    }

    public function isArabic(string $text): bool
    {
        $arabicChars = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
        $totalChars  = mb_strlen(preg_replace('/\s+/', '', $text), 'UTF-8');
        return $totalChars > 0 && ($arabicChars / $totalChars) > 0.25;
    }

    private function normalizeArabicChars(string $text): string
    {
        $text = str_replace(['أ','إ','آ','ٱ'], 'ا', $text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $text = str_replace('ـ', '', $text);
        return mb_strtolower(trim($text), 'UTF-8');
    }

    /**
     * BUG 2 FIX — Helper
     *
     * يحذف أداة التعريف "ال" من بداية الكلمة قبل البحث في AR_PRODUCT_MAP
     * "الايفون" → "ايفون"  → يطابق AR_PRODUCT_MAP['ايفون'] = 'iphone'
     * "الآيفون" بعد normalizeArabicChars تصبح "الايفون" أصلاً (أ/إ/آ → ا)
     * "السامسونغ" → "سامسونغ" → يطابق AR_PRODUCT_MAP['سامسونغ'] = 'samsung'
     *
     * حماية: لا نحذف "ال" من كلمات قصيرة (≤3 أحرف) لتفادي تكسير كلمات
     * لا تبدأ فعلياً بأداة التعريف (نادر لكن وقائي)
     */
    private function stripDefiniteArticle(string $word): string
    {
        if (mb_strlen($word, 'UTF-8') > 3 && mb_substr($word, 0, 2, 'UTF-8') === 'ال') {
            return mb_substr($word, 2, null, 'UTF-8');
        }
        return $word;
    }

    private function splitArabicWords(string $text): array
    {
        $words = preg_split('/[\s,،.]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_filter($words, fn($w) => mb_strlen(trim($w), 'UTF-8') >= 1));
    }

    private function emptyResult(string $query, float $confidence, string $source): array
    {
        return [
            'correctedQuery'   => '',
            'include'          => [],
            'exclude'          => [],
            'expandedKeywords' => [],
            'intent'           => 'general',
            'confidence'       => $confidence,
            'originalQuery'    => $query,
            'source'           => $source,
        ];
    }
}