<?php

namespace App\Domains\Search\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AIQueryEnhancer
 *
 * Pipeline:
 *   raw query
 *   → isGibberish?        → emptyResult
 *   → isArabic?           → processArabicLocally()
 *   → callOpenRouterAPI() → parseStructuredResponse()
 *   → [on failure]        → localFallback()
 *
 * Output shape (ثابت دائماً):
 * [
 *   correctedQuery   => string,
 *   include          => string[],   ← ما يُبحث عنه
 *   exclude          => string[],   ← ما يُستثنى
 *   expandedKeywords => string[],
 *   intent           => string,
 *   confidence       => float,
 *   source           => string,
 *   originalQuery    => string,
 * ]
 */
class AIQueryEnhancer
{
    private const CACHE_TTL  = 3600;
    private const API_TIMEOUT = 10;
    private const API_URL     = 'https://openrouter.ai/api/v1/chat/completions';

    // ─── قاموس تصحيح الأخطاء الإملائية ───────────────────────────────
    private const TYPO_DICTIONARY = [
        'iphoen'   => 'iphone',   'ipone'    => 'iphone',
        'iphon'    => 'iphone',   'iphne'    => 'iphone',
        'ifone'    => 'iphone',   'iphone'   => 'iphone',
        'samsng'   => 'samsung',  'samsong'  => 'samsung',
        'sasmung'  => 'samsung',  'smasung'  => 'samsung',
        'samsnug'  => 'samsung',  'samsumg'  => 'samsung',
        'googel'   => 'google',   'gogle'    => 'google',
        'laptp'    => 'laptop',   'labtop'   => 'laptop',
        'leptop'   => 'laptop',   'laptob'   => 'laptop',
        'latpop'   => 'laptop',   'lpatop'   => 'laptop',
        'macbok'   => 'macbook',  'makbook'  => 'macbook',
        'macbbok'  => 'macbook',  'macboo'   => 'macbook',
        'androd'   => 'android',  'androied' => 'android',
        'androdi'  => 'android',  'andriod'  => 'android',
        'prie'     => 'price',    'prise'    => 'price',
        'prcie'    => 'price',    'rpice'    => 'price',
        'cheep'    => 'cheap',    'chep'     => 'cheap',
        'chap'     => 'cheap',    'chepa'    => 'cheap',
        'phoen'    => 'phone',    'fone'     => 'phone',
        'phon'     => 'phone',    'pone'     => 'phone',
        'tabelt'   => 'tablet',   'tabet'    => 'tablet',
        'tablat'   => 'tablet',   'tabler'   => 'tablet',
        'samsun'   => 'samsung',  'galxy'    => 'galaxy',
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

    // ─── ترجمة AR → EN ────────────────────────────────────────────────
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
    ];

    // ─── نفي عربي ─────────────────────────────────────────────────────
    private const AR_NEGATION_PATTERNS = [
        'ما بدي', 'ما اريد', 'ما أريد', 'ما ابغى', 'ما أبغى',
        'لا اريد', 'لا أريد', 'لا ابغى', 'لا أبغى',
        'مش عايز', 'مش عايزة', 'مو بادي', 'مو عايز',
        'بدون', 'بدوني', 'غير', 'ماعدا', 'سوى', 'عدا', 'إلا',
        'مبغاش', 'مابغاش',
    ];

    // ─── حشو عربي ─────────────────────────────────────────────────────
    private const AR_FILLER_WORDS = [
        'بدي', 'ودي', 'ابي', 'أبي', 'نفسي', 'محتاج', 'محتاجة',
        'حابب', 'حابة', 'عايز', 'عايزة', 'ابغى', 'أبغى',
        'اريد', 'أريد', 'ابغاه', 'ابيه', 'بغيت', 'عندي',
        'يا', 'هلا', 'ممكن', 'لو', 'فيه', 'وين',
    ];

    // ─────────────────────────────────────────────────────────────────

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

        // Gibberish: ترفض فوراً بدون API call
        if ($this->isGibberish($query)) {
            Log::debug('AIQueryEnhancer: gibberish detected', ['query' => $query]);
            $result = $this->emptyResult($query, 0.04, 'gibberish');
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return $result;
        }

        // Arabic: معالجة محلية بدون API
        if ($this->isArabic($query)) {
            Log::debug('AIQueryEnhancer: arabic detected, using local processing', ['query' => $query]);
            $result = $this->processArabicLocally($query);
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return array_merge($result, ['source' => 'arabic_local']);
        }

        // English: جرّب API أولاً ثم local fallback
        try {
            $result = $this->callOpenRouterAPI($query, $language);
            Cache::put($cacheKey, $result, self::CACHE_TTL);

            Log::info('AIQueryEnhancer: API success', [
                'original'   => $query,
                'corrected'  => $result['correctedQuery'],
                'include'    => $result['include'],
                'exclude'    => $result['exclude'],
                'confidence' => $result['confidence'],
            ]);

            return array_merge($result, ['source' => 'api']);

        } catch (\Throwable $e) {
            Log::warning('AIQueryEnhancer: API failed, using local fallback', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);

            $result = $this->processEnglishLocally($query);
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return array_merge($result, ['source' => 'local_fallback']);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // API
    // ─────────────────────────────────────────────────────────────────

    private function callOpenRouterAPI(string $query, string $language): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openrouter.key'),
            'Content-Type'  => 'application/json',
        ])
        ->timeout(self::API_TIMEOUT)
        ->post(self::API_URL, [
            'model'      => config('services.openrouter.model', 'mistralai/mistral-7b-instruct'),
            'max_tokens' => 400,
            'messages'   => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user',   'content' => $this->userPrompt($query, $language)],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'OpenRouter error: ' . $response->status() . ' ' . $response->body()
            );
        }

        $content = $response->json('choices.0.message.content');

        if (empty($content)) {
            throw new \RuntimeException('OpenRouter: empty content');
        }

        return $this->parseApiResponse($content, $query);
    }

    // ─────────────────────────────────────────────────────────────────
    // Prompts
    // ─────────────────────────────────────────────────────────────────

    private function systemPrompt(): string
    {
        return <<<'SYSTEM'
You are a multilingual search query interpreter.

Return ONLY valid JSON. No explanation. No markdown. No extra text.

Rules:
1. Correct spelling (iphoen→iphone, samsng→samsung, laptp→laptop).
2. Detect negation. Move negated product/item to "exclude".
   Arabic: ما بدي, بدون, غير, مو بادي, لا اريد
   English: without, not, no, except
3. "include" = what user WANTS. Must be English words only.
4. "exclude" = what user does NOT want. English only.
5. Translate Arabic brand/product names to English in output.
6. Numbers after negation go to "exclude" as strings.
7. Drop filler/stop words (بدي, ودي, ابي, want, need, looking).
8. Gibberish = confidence < 0.15, empty arrays.
9. Detect intent: buy|learn|repair|compare|general

JSON schema:
{"correctedQuery":"","include":[],"exclude":[],"expandedKeywords":[],"intent":"general","confidence":0.0}
SYSTEM;
    }

    private function userPrompt(string $query, string $language): string
    {
        $lang = match ($language) {
            'ar'    => 'Arabic',
            'fr'    => 'French',
            default => 'English',
        };

        return <<<PROMPT
Language: {$lang}

Examples:
Q: "iphoen" → {"correctedQuery":"iphone","include":["iphone"],"exclude":[],"expandedKeywords":["apple phone"],"intent":"general","confidence":0.93}
Q: "samsng price" → {"correctedQuery":"samsung price","include":["samsung","price"],"exclude":[],"expandedKeywords":["samsung deal","samsung cost"],"intent":"buy","confidence":0.91}
Q: "ما بدي ايفون 15" → {"correctedQuery":"iphone","include":["iphone"],"exclude":["15"],"expandedKeywords":["apple phone"],"intent":"buy","confidence":0.92}
Q: "بدون سامسونج" → {"correctedQuery":"","include":[],"exclude":["samsung"],"expandedKeywords":[],"intent":"general","confidence":0.88}
Q: "ارخص لابتوب" → {"correctedQuery":"cheap laptop","include":["laptop","cheap"],"exclude":[],"expandedKeywords":["affordable laptop","budget laptop"],"intent":"buy","confidence":0.90}
Q: "cheap iphone" → {"correctedQuery":"cheap iphone","include":["iphone","cheap"],"exclude":[],"expandedKeywords":["affordable iphone","iphone price"],"intent":"buy","confidence":0.95}
Q: "asdasdadasd" → {"correctedQuery":"","include":[],"exclude":[],"expandedKeywords":[],"intent":"general","confidence":0.03}

Query: "{$query}"
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────
    // Parse API Response
    // ─────────────────────────────────────────────────────────────────

    private function parseApiResponse(string $content, string $originalQuery): array
    {
        // إزالة markdown fences
        $clean = preg_replace('/```(?:json)?\s*|\s*```/', '', trim($content));
        $clean = trim($clean);

        // استخراج أول JSON object
        if (preg_match('/\{.*?\}/s', $clean, $m)) {
            $clean = $m[0];
        }

        $parsed = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            Log::warning('AIQueryEnhancer: JSON parse failed', [
                'raw'   => substr($content, 0, 200),
                'error' => json_last_error_msg(),
            ]);
            // fallback محلي بدل إرجاع بيانات خاطئة
            return $this->processEnglishLocally($originalQuery);
        }

        $include    = $this->sanitizeArray($parsed['include']          ?? []);
        $exclude    = $this->sanitizeArray($parsed['exclude']          ?? []);
        $expanded   = $this->sanitizeArray($parsed['expandedKeywords'] ?? []);
        $corrected  = trim($parsed['correctedQuery'] ?? '');
        $intent     = $this->sanitizeIntent($parsed['intent'] ?? 'general');
        $confidence = min(1.0, max(0.0, (float) ($parsed['confidence'] ?? 0.5)));

        // إذا include فارغة لكن corrected موجود → اشتق include منه
        if (empty($include) && ! empty($corrected)) {
            $include = array_values(array_filter(
                explode(' ', $corrected),
                fn($w) => mb_strlen(trim($w), 'UTF-8') >= 2
            ));
        }

        // correctedQuery النهائي = include مدمجة
        $finalCorrected = ! empty($include) ? implode(' ', $include) : $corrected;

        return [
            'correctedQuery'   => $finalCorrected,
            'include'          => $include,
            'exclude'          => $exclude,
            'expandedKeywords' => $expanded,
            'intent'           => $intent,
            'confidence'       => $confidence,
            'originalQuery'    => $originalQuery,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Arabic Local Processing
    // ─────────────────────────────────────────────────────────────────

    private function processArabicLocally(string $query): array
    {
        $normalized = $this->normalizeArabicChars($query);

        $exclude   = [];
        $cleanText = $normalized;

        // فرز الـ patterns تنازلياً بالطول
        $patterns = self::AR_NEGATION_PATTERNS;
        usort($patterns, fn($a, $b) => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

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
                if (in_array($word, self::AR_FILLER_WORDS, true)) continue;

                if (is_numeric($word)) {
                    $exclude[] = $word;
                } else {
                    $exclude[] = self::AR_PRODUCT_MAP[$word] ?? $word;
                }
            }

            // احتفظ فقط بما قبل النفي
            $cleanText = trim(mb_substr($cleanText, 0, $pos, 'UTF-8'));
            break;
        }

        // بناء include من ما تبقى
        $include  = [];
        $fillers  = array_flip(self::AR_FILLER_WORDS);
        $words    = $this->splitArabicWords($cleanText);

        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word, 'UTF-8') < 2) continue;
            if (isset($fillers[$word])) continue;

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

    // ─────────────────────────────────────────────────────────────────
    // English Local Processing (Levenshtein)
    // ─────────────────────────────────────────────────────────────────

    private function processEnglishLocally(string $query): array
    {
        $words     = preg_split('/\s+/', mb_strtolower(trim($query), 'UTF-8'));
        $corrected = [];
        $hadFix    = false;

        foreach ($words as $word) {
            $word = preg_replace('/[^a-z0-9]/i', '', $word);
            if (mb_strlen($word) < 2) continue;

            // 1. قاموس مباشر
            if (isset(self::TYPO_DICTIONARY[$word])) {
                $fixed = self::TYPO_DICTIONARY[$word];
                if ($fixed !== $word) $hadFix = true;
                $corrected[] = $fixed;
                continue;
            }

            // 2. Levenshtein fuzzy
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
            if (abs(strlen($dictWord) - $wordLen) > $maxAllowed) continue;

            $dist = levenshtein($word, $dictWord);
            if ($dist < $bestDist && $dist <= $maxAllowed) {
                $bestDist  = $dist;
                $bestMatch = $correction;
            }
        }

        return $bestMatch;
    }

    // ─────────────────────────────────────────────────────────────────
    // Public Helpers (مستخدمة من SearchEntriesAction)
    // ─────────────────────────────────────────────────────────────────

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

    // ─────────────────────────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────────────────────────

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
        return array_values(array_filter($words, fn($w) => mb_strlen(trim($w), 'UTF-8') >= 1));
    }

    private function detectArabicIntent(string $text): string
    {
        $buySignals    = ['شراء', 'اشتري', 'سعر', 'أسعار', 'رخيص', 'ارخص', 'ثمن', 'عرض', 'خصم'];
        $repairSignals = ['إصلاح', 'تصليح', 'صيانة', 'حجز', 'موعد', 'خدمة', 'تركيب'];
        $learnSignals  = ['شرح', 'دليل', 'كيف', 'مراجعة', 'تعلم', 'أخبار'];

        foreach ($buySignals    as $s) { if (str_contains($text, $s)) return 'buy';    }
        foreach ($repairSignals as $s) { if (str_contains($text, $s)) return 'repair'; }
        foreach ($learnSignals  as $s) { if (str_contains($text, $s)) return 'learn';  }

        return 'general';
    }

    private function sanitizeArray(mixed $input): array
    {
        if (! is_array($input)) return [];
        return array_values(array_filter(
            array_map(fn($v) => is_string($v) ? trim($v) : null, $input),
            fn($v) => $v !== null && mb_strlen($v, 'UTF-8') >= 1
        ));
    }

    private function sanitizeIntent(string $intent): string
    {
        return in_array($intent, ['buy', 'learn', 'repair', 'compare', 'general'], true)
            ? $intent
            : 'general';
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