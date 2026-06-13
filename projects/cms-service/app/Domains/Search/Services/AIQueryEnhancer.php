<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\Services\AI\AIProviderInterface;
use App\Domains\Search\Services\AI\GeminiProvider;
use App\Domains\Search\Services\AI\OpenRouterProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AIQueryEnhancer
 *
 * ─── ما تغيّر ─────────────────────────────────────────────────────────────
 *
 * الإصدار الجديد يستخدم AIProviderInterface abstraction:
 *   Primary:  GeminiProvider  (GEMINI_API_KEY)
 *   Fallback: OpenRouterProvider (OPENROUTER_KEY)
 *
 * إذا Gemini فشل (timeout, invalid JSON, error) → ينتقل فوراً لـ OpenRouter.
 * إذا كلاهما فشل → local processing كما كان سابقاً.
 *
 * ─── ما لم يتغيّر ─────────────────────────────────────────────────────────
 *
 * Output shape نفسه تماماً (لا تكسر SearchEntriesAction):
 * {
 *   correctedQuery, include[], exclude[],
 *   expandedKeywords[], intent, confidence, source, originalQuery
 * }
 *
 * isGibberish() و isArabic() public — مُستخدمتان من SearchEntriesAction.
 * Local Arabic + English processing موجودان كـ fallback نهائي.
 * Cache TTL = 3600 ثانية.
 */
class AIQueryEnhancer
{
    private const CACHE_TTL = 3600; // 60 دقيقة

    // ─── قاموس تصحيح الأخطاء الإملائية (لـ local fallback) ──────────────
    private const TYPO_DICTIONARY = [
        'iphoen'   => 'iphone',   'ipone'    => 'iphone',
        'iphon'    => 'iphone',   'iphne'    => 'iphone',
        'ifone'    => 'iphone',   'samsng'   => 'samsung',
        'samsong'  => 'samsung',  'sasmung'  => 'samsung',
        'smasung'  => 'samsung',  'samsnug'  => 'samsung',
        'samsumg'  => 'samsung',  'googel'   => 'google',
        'gogle'    => 'google',   'laptp'    => 'laptop',
        'labtop'   => 'laptop',   'leptop'   => 'laptop',
        'laptob'   => 'laptop',   'latpop'   => 'laptop',
        'lpatop'   => 'laptop',   'macbok'   => 'macbook',
        'makbook'  => 'macbook',  'macbbok'  => 'macbook',
        'androd'   => 'android',  'androied' => 'android',
        'androdi'  => 'android',  'andriod'  => 'android',
        'prie'     => 'price',    'prise'    => 'price',
        'prcie'    => 'price',    'rpice'    => 'price',
        'cheep'    => 'cheap',    'chep'     => 'cheap',
        'chap'     => 'cheap',    'chepa'    => 'cheap',
        'phoen'    => 'phone',    'fone'     => 'phone',
        'phon'     => 'phone',    'pone'     => 'phone',
        'tabelt'   => 'tablet',   'tabet'    => 'tablet',
        'tablat'   => 'tablet',   'galxy'    => 'galaxy',
        'gallaxy'  => 'galaxy',   'galaxi'   => 'galaxy',
        'nokea'    => 'nokia',    'nokiya'   => 'nokia',
        'pixle'    => 'pixel',    'pxiel'    => 'pixel',
        'wireles'  => 'wireless', 'bluetoth' => 'bluetooth',
        'chager'   => 'charger',  'chargr'   => 'charger',
        'baterry'  => 'battery',  'batery'   => 'battery',
        'camra'    => 'camera',   'camear'   => 'camera',
        'screeen'  => 'screen',   'scren'    => 'screen',
        'headfone' => 'headphone','earphon'  => 'earphone',
        'hedphone' => 'headphone','wirless'  => 'wireless',
    ];

    // ─── ترجمة AR → EN ────────────────────────────────────────────────────
    private const AR_PRODUCT_MAP = [
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
        'مونتاج'  => 'video editing', 'تصوير'  => 'photography',
        'جامعة'   => 'student',       'برمجة'  => 'programming',
        'العاب'   => 'gaming',        'الألعاب'=> 'gaming',
    ];

    private const AR_NEGATION_PATTERNS = [
        'ما بدي', 'ما اريد', 'ما أريد', 'ما ابغى', 'ما أبغى',
        'لا اريد', 'لا أريد', 'لا ابغى', 'لا أبغى',
        'مش عايز', 'مش عايزة', 'مو بادي', 'مو عايز',
        'بدون', 'بدوني', 'غير', 'ماعدا', 'سوى', 'عدا', 'إلا',
        'مبغاش', 'مابغاش',
    ];

    private const AR_FILLER_WORDS = [
        'بدي', 'ودي', 'ابي', 'أبي', 'نفسي', 'محتاج', 'محتاجة',
        'حابب', 'حابة', 'عايز', 'عايزة', 'ابغى', 'أبغى',
        'اريد', 'أريد', 'ابغاه', 'ابيه', 'بغيت', 'عندي',
        'يا', 'هلا', 'ممكن', 'لو', 'فيه', 'وين', 'كتير',
        'منيح', 'حلو', 'زبالة', 'شي', 'حاجة',
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Constructor — يُبنى الـ provider chain هنا
    // ─────────────────────────────────────────────────────────────────────

    /**
     * @param AIProviderInterface[] $providers
     */
    public function __construct(
        private ?array $providers = null
    ) {
        // إذا لم يُمرَّر providers → ننشئها من الـ config
        if ($this->providers === null) {
            $this->providers = $this->buildProviderChain();
        }
    }

    /**
     * بناء provider chain من الـ config
     * الترتيب: Gemini أولاً (إذا مفعّل) → OpenRouter
     */
    private function buildProviderChain(): array
    {
        $providers = [];

        $geminiEnabled = ! empty(config('services.gemini.key'));
        $openrouterEnabled = ! empty(config('services.openrouter.key'));

        if ($geminiEnabled) {
            $providers[] = new GeminiProvider();
        }

        if ($openrouterEnabled) {
            $providers[] = new OpenRouterProvider();
        }

        return $providers;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Public API — نفس الـ signature السابق تماماً
    // ─────────────────────────────────────────────────────────────────────

    public function enhance(string $query, string $language): array
    {
        $query = trim($query);

        if (empty($query)) {
            return $this->emptyResult($query, 0.0, 'empty_input');
        }

        $cacheKey = $this->buildCacheKey($query, $language);
        $cached   = Cache::get($cacheKey);

        if ($cached !== null) {
            Log::debug('AIQueryEnhancer: cache hit', ['query' => $query]);
            return array_merge($cached, ['source' => 'cache']);
        }

        // ─── Gate 1: Gibberish → لا API ──────────────────────────────────
        if ($this->isGibberish($query)) {
            Log::debug('AIQueryEnhancer: gibberish, skipping API', ['query' => $query]);
            $result = $this->emptyResult($query, 0.04, 'gibberish');
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return $result;
        }

        // ─── Gate 2: Arabic local fast path ──────────────────────────────
        // إذا عربي خالص ولا Gemini مفعّل → معالجة محلية فورية
        if ($this->isArabic($query) && empty(config('services.gemini.key')) && empty(config('services.openrouter.key'))) {
            $result = $this->processArabicLocally($query);
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return array_merge($result, ['source' => 'arabic_local']);
        }

        // ─── Gate 3: AI Provider Chain ───────────────────────────────────
        $aiResult = $this->tryProviderChain($query, $language);

        if ($aiResult !== null) {
            // تحويل output الجديد {normalized_query, confidence, reasoning}
            // إلى output القديم {correctedQuery, include[], exclude[], ...}
            // حتى لا نكسر SearchEntriesAction
            $result = $this->convertProviderOutput($aiResult, $query);
            Cache::put($cacheKey, $result, self::CACHE_TTL);

            Log::info('AIQueryEnhancer: AI provider succeeded', [
                'original'   => $query,
                'normalized' => $aiResult['normalized_query'],
                'confidence' => $aiResult['confidence'],
                'provider'   => $aiResult['_provider'] ?? 'unknown',
            ]);

            return array_merge($result, ['source' => $aiResult['_provider'] ?? 'api']);
        }

        // ─── Gate 4: Local Fallback ───────────────────────────────────────
        if ($this->isArabic($query)) {
            $result = $this->processArabicLocally($query);
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return array_merge($result, ['source' => 'arabic_local']);
        }

        $result = $this->processEnglishLocally($query);
        Cache::put($cacheKey, $result, self::CACHE_TTL);
        return array_merge($result, ['source' => 'local_fallback']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Provider Chain
    // ─────────────────────────────────────────────────────────────────────

    /**
     * يُجرب providers بالترتيب:
     * Gemini → إذا فشل → OpenRouter → إذا فشل → null
     *
     * يُضيف _provider للـ result للـ logging
     */
    private function tryProviderChain(string $query, string $language): ?array
    {
        foreach ($this->providers as $provider) {
            try {
                $result = $provider->normalize($query, $language);

                // confidence check — تجاهل الـ response إذا منخفضة جداً
                if (($result['confidence'] ?? 0.0) < 0.50) {
                    Log::info('AIQueryEnhancer: provider low confidence, trying next', [
                        'provider'   => $provider->name(),
                        'confidence' => $result['confidence'],
                        'query'      => $query,
                    ]);
                    continue;
                }

                // normalized_query فارغة → لا فائدة
                if (empty(trim($result['normalized_query'] ?? ''))) {
                    Log::info('AIQueryEnhancer: provider returned empty query', [
                        'provider' => $provider->name(),
                    ]);
                    continue;
                }

                $result['_provider'] = $provider->name();
                return $result;

            } catch (\Throwable $e) {
                Log::warning("AIQueryEnhancer: provider [{$provider->name()}] failed", [
                    'error' => $e->getMessage(),
                    'query' => $query,
                ]);
                // انتقل للـ provider التالي
                continue;
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Output Conversion
    // ─────────────────────────────────────────────────────────────────────

    /**
     * يُحوّل output الجديد (normalized_query, confidence, reasoning)
     * إلى output القديم (correctedQuery, include[], exclude[], ...)
     *
     * هذا يضمن عدم كسر SearchEntriesAction الذي يتوقع الـ format القديم.
     */
    private function convertProviderOutput(array $providerResult, string $originalQuery): array
    {
        $normalized = trim($providerResult['normalized_query'] ?? '');
        $confidence = $providerResult['confidence'] ?? 0.0;

        // تقسيم الـ normalized_query إلى include terms
        $includeTerms = array_values(array_filter(
            explode(' ', $normalized),
            fn ($w) => mb_strlen(trim($w), 'UTF-8') >= 2
        ));

        return [
            'correctedQuery'   => $normalized,
            'include'          => $includeTerms,
            'exclude'          => [], // الـ providers الجدد لا يتعاملون مع exclude (هذا دور ArabicNormalizer)
            'expandedKeywords' => [],
            'intent'           => 'general',
            'confidence'       => $confidence,
            'originalQuery'    => $originalQuery,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Local Arabic Processing
    // ─────────────────────────────────────────────────────────────────────

    private function processArabicLocally(string $query): array
    {
        $normalized = $this->normalizeArabicChars($query);

        $exclude   = [];
        $cleanText = $normalized;

        $patterns = self::AR_NEGATION_PATTERNS;
        usort($patterns, fn ($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        foreach ($patterns as $pattern) {
            $pos = mb_strpos($cleanText, $pattern, 0, 'UTF-8');
            if ($pos === false) {
                continue;
            }

            $afterOffset = $pos + mb_strlen($pattern, 'UTF-8');
            $afterText   = trim(mb_substr($cleanText, $afterOffset, null, 'UTF-8'));
            $afterWords  = $this->splitArabicWords($afterText);

            foreach (array_slice($afterWords, 0, 3) as $word) {
                $word = trim($word);
                if (in_array($word, self::AR_FILLER_WORDS, true)) {
                    continue;
                }
                if (is_numeric($word)) {
                    $exclude[] = $word;
                } else {
                    $exclude[] = self::AR_PRODUCT_MAP[$word] ?? $word;
                }
            }

            $cleanText = trim(mb_substr($cleanText, 0, $pos, 'UTF-8'));
            break;
        }

        $include  = [];
        $fillers  = array_flip(self::AR_FILLER_WORDS);
        $words    = $this->splitArabicWords($cleanText);

        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') < 2) {
                continue;
            }
            if (isset($fillers[$word])) {
                continue;
            }
            $translated = self::AR_PRODUCT_MAP[$word] ?? $word;
            if (! empty($translated)) {
                $include[] = $translated;
            }
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
            'intent'           => $this->detectArabicIntent($normalized),
            'confidence'       => ! empty($exclude) ? 0.87 : 0.72,
            'originalQuery'    => $query,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Local English Processing (Levenshtein)
    // ─────────────────────────────────────────────────────────────────────

    private function processEnglishLocally(string $query): array
    {
        $words     = preg_split('/\s+/', mb_strtolower(trim($query), 'UTF-8'));
        $corrected = [];
        $hadFix    = false;

        foreach ($words as $word) {
            $word = preg_replace('/[^a-z0-9]/i', '', $word);
            if (mb_strlen($word) < 2) {
                continue;
            }
            if (isset(self::TYPO_DICTIONARY[$word])) {
                $fixed = self::TYPO_DICTIONARY[$word];
                if ($fixed !== $word) {
                    $hadFix = true;
                }
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

    private function levenshteinCorrect(string $word): ?string
    {
        $wordLen    = strlen($word);
        $bestMatch  = null;
        $bestDist   = PHP_INT_MAX;
        $maxAllowed = $wordLen <= 5 ? 2 : ($wordLen <= 8 ? 3 : 4);

        foreach (self::TYPO_DICTIONARY as $dictWord => $correction) {
            if (abs(strlen($dictWord) - $wordLen) > $maxAllowed) {
                continue;
            }
            $dist = levenshtein($word, $dictWord);
            if ($dist < $bestDist && $dist <= $maxAllowed) {
                $bestDist  = $dist;
                $bestMatch = $correction;
            }
        }

        return $bestMatch;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Public Helpers — مُستخدمتان من SearchEntriesAction (لا تتغيران)
    // ─────────────────────────────────────────────────────────────────────

    public function isGibberish(string $text): bool
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        if (empty($text) || mb_strlen($text, 'UTF-8') < 4) {
            return false;
        }
        if ($this->isArabic($text)) {
            return false;
        }

        $letters = preg_replace('/[^a-z]/i', '', $text);
        $len     = strlen($letters);

        if ($len < 4) {
            return false;
        }

        $vowels = preg_replace('/[^aeiou]/i', '', $letters);
        if (strlen($vowels) / $len < 0.08) {
            return true;
        }
        if (preg_match('/[^aeiou\s]{6,}/i', $letters)) {
            return true;
        }

        return false;
    }

    public function isArabic(string $text): bool
    {
        $arabicChars = preg_match_all('/[\x{0600}-\x{06FF}]/u', $text);
        $totalChars  = mb_strlen(preg_replace('/\s+/', '', $text), 'UTF-8');

        return $totalChars > 0 && ($arabicChars / $totalChars) > 0.25;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function normalizeArabicChars(string $text): string
    {
        $text = str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $text);
        $text = str_replace('ـ', '', $text);

        return mb_strtolower(trim($text), 'UTF-8');
    }

    private function splitArabicWords(string $text): array
    {
        $words = preg_split('/[\s,،.]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            $words,
            fn ($w) => mb_strlen(trim($w), 'UTF-8') >= 1
        ));
    }

    private function detectArabicIntent(string $text): string
    {
        $buySignals    = ['شراء', 'اشتري', 'سعر', 'أسعار', 'رخيص', 'ارخص', 'ثمن', 'عرض', 'خصم'];
        $repairSignals = ['إصلاح', 'تصليح', 'صيانة', 'حجز', 'موعد', 'خدمة', 'تركيب'];
        $learnSignals  = ['شرح', 'دليل', 'كيف', 'مراجعة', 'تعلم', 'أخبار'];

        foreach ($buySignals as $s) {
            if (str_contains($text, $s)) return 'buy';
        }
        foreach ($repairSignals as $s) {
            if (str_contains($text, $s)) return 'repair';
        }
        foreach ($learnSignals as $s) {
            if (str_contains($text, $s)) return 'learn';
        }

        return 'general';
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

    private function buildCacheKey(string $query, string $language): string
    {
        return 'ai_enhance:' . md5(mb_strtolower(trim($query), 'UTF-8') . ':' . $language);
    }
}