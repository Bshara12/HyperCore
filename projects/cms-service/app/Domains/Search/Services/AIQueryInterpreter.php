<?php

namespace App\Domains\Search\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * يُحل محل AIQueryEnhancer القديم
 *
 * الفرق الجوهري:
 *   القديم → يطلب "correctedQuery" كـ string كامل
 *   الجديد → يطلب بنية منظمة: include/exclude/intent/tokens
 *
 * هذا يمنع المشكلة الأساسية:
 *   "ما بدي ايفون" → لا يُحوَّل لـ "what do want an iphone"
 *                  → يُحوَّل لـ { exclude: ["iphone"], intent: "avoid" }
 */
class AIQueryInterpreter
{
    private const CACHE_TTL = 3600;
    private const TIMEOUT   = 8;
    private const API_URL   = 'https://openrouter.ai/api/v1/chat/completions';

    // ─────────────────────────────────────────────────────────────────

    /**
     * تحليل الـ query وإرجاع بنية منظمة
     *
     * @return array{
     *   include:    string[],   // كلمات البحث الأساسية
     *   exclude:    string[],   // كلمات الاستبعاد
     *   intent:     string,     // buy|compare|learn|avoid|general
     *   expanded:   string[],   // مرادفات/توسعات
     *   corrected:  string,     // الـ query المُصحَّح (للعرض فقط)
     *   confidence: float,
     *   source:     string,
     * }
     */
    public function interpret(string $query, string $language): array
    {
        $cacheKey = 'ai_interpret:' . md5(mb_strtolower(trim($query)) . ':' . $language);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return array_merge($cached, ['source' => 'cache']);
        }

        Log::info('AIQueryInterpreter: calling API', [
            'query'    => $query,
            'language' => $language,
        ]);

        try {
            $result = $this->callAPI($query, $language);
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            return array_merge($result, ['source' => 'api']);

        } catch (\Throwable $e) {
            Log::error('AIQueryInterpreter: API failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return $this->buildFallback($query);
        }
    }

    // ─────────────────────────────────────────────────────────────────

    private function callAPI(string $query, string $language): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openrouter.key'),
            'Content-Type'  => 'application/json',
        ])
        ->timeout(self::TIMEOUT)
        ->post(self::API_URL, [
            'model'      => config('services.openrouter.model', 'mistralai/mistral-7b-instruct'),
            'max_tokens' => 400,
            'messages'   => [
                ['role' => 'user', 'content' => $this->buildPrompt($query, $language)],
            ],
        ]);

        if (!$response->successful()) {
            Log::error('AIQueryInterpreter: API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('API error: ' . $response->status());
        }

        $content = $response->json()['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            throw new \RuntimeException('Empty API response');
        }

        return $this->parseResponse($content, $query);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Prompt مُصمَّم لإرجاع TOKENS وليس جمل
     *
     * الفرق الجوهري عن الـ Prompt القديم:
     *   القديم: "correct the query" → يُنتج جمل كاملة
     *   الجديد: "extract structured intent" → يُنتج tokens فقط
     *
     * مثال المدخل:  "ما بدي ايفون 15"
     * مثال المخرج:
     * {
     *   "include": ["iphone"],
     *   "exclude": ["15"],
     *   "intent": "avoid",
     *   "expanded": [],
     *   "corrected": "iphone without 15",
     *   "confidence": 0.9
     * }
     */
    private function buildPrompt(string $query, string $language): string
    {
        $langName = match($language) {
            'ar'    => 'Arabic',
            'fr'    => 'French',
            default => 'English',
        };

        return <<<PROMPT
You are a search query parser for a product/content database.

Input query: "{$query}"
Input language: {$langName}

Your job: Extract structured search intent. Return ONLY valid JSON.

Rules:
1. "include": search tokens to find (array of single words or short phrases, max 5)
2. "exclude": terms user wants to AVOID (array of single words, max 3)
3. "intent": one of: "buy", "compare", "learn", "avoid", "repair", "general"
4. "expanded": 2-3 alternative tokens/synonyms for include terms (NOT full sentences)
5. "corrected": spell-corrected version as SHORT search string (max 5 words, NO explanation)
6. "confidence": 0.0 to 1.0

Critical rules:
- NEVER put full sentences in include, exclude, or expanded
- NEVER add words like "buy", "price", "deals" unless user said them
- If query has negation ("ما بدي", "don't want", "without", "غير"): move negated term to "exclude"
- If query is gibberish/random: set include=[], confidence=0.1
- Translate Arabic product names to English in the output

Examples:
Input: "ما بدي ايفون 15"
Output: {"include":["iphone"],"exclude":["15"],"intent":"avoid","expanded":["apple phone","smartphone"],"corrected":"iphone -15","confidence":0.9}

Input: "iphoen"
Output: {"include":["iphone"],"exclude":[],"intent":"general","expanded":["apple phone"],"corrected":"iphone","confidence":0.85}

Input: "legh fid hgphgm"
Output: {"include":[],"exclude":[],"intent":"general","expanded":[],"corrected":"","confidence":0.1}

Input: "iphone 15 price compare"
Output: {"include":["iphone","15","price"],"exclude":[],"intent":"compare","expanded":["apple phone","iphone cost"],"corrected":"iphone 15 price","confidence":0.95}

Now parse: "{$query}"
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────

    private function parseResponse(string $content, string $originalQuery): array
    {
        $clean  = preg_replace('/```(?:json)?\s*|\s*```/', '', trim($content));
        $parsed = json_decode(trim($clean), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            Log::warning('AIQueryInterpreter: parse failed', [
                'content' => $content,
                'error'   => json_last_error_msg(),
            ]);
            return $this->buildFallback($originalQuery);
        }

        $include    = $this->sanitizeTokens($parsed['include']    ?? []);
        $exclude    = $this->sanitizeTokens($parsed['exclude']    ?? []);
        $expanded   = $this->sanitizeTokens($parsed['expanded']   ?? []);
        $intent     = $this->sanitizeIntent($parsed['intent']     ?? 'general');
        $corrected  = trim(strip_tags($parsed['corrected']         ?? ''));
        $confidence = min(1.0, max(0.0, (float) ($parsed['confidence'] ?? 0.5)));

        // Safety: إذا include فارغة لكن confidence عالي → استخدم corrected
        if (empty($include) && !empty($corrected) && $confidence > 0.5) {
            $include = array_filter(
                explode(' ', mb_strtolower($corrected)),
                fn($w) => mb_strlen($w) >= 2
            );
            $include = array_values($include);
        }

        Log::debug('AIQueryInterpreter: parsed result', [
            'original'   => $originalQuery,
            'include'    => $include,
            'exclude'    => $exclude,
            'intent'     => $intent,
            'confidence' => $confidence,
        ]);

        return [
            'include'    => $include,
            'exclude'    => $exclude,
            'intent'     => $intent,
            'expanded'   => $expanded,
            'corrected'  => $corrected,
            'confidence' => $confidence,
        ];
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * تنظيف الـ tokens - يمنع الجمل الطويلة
     *
     * إذا AI أرسل "apple iphone smartphone" كـ token واحد → نُقسِّمه
     * إذا أرسل جملة طويلة → نأخذ أول كلمتين فقط
     */
    private function sanitizeTokens(array $tokens): array
    {
        $result = [];

        foreach ($tokens as $token) {
            $token = trim(mb_strtolower($token, 'UTF-8'));

            if (empty($token) || mb_strlen($token) < 2) {
                continue;
            }

            // إذا أكثر من كلمتين → خذ أول كلمتين فقط
            $words = array_filter(explode(' ', $token), fn($w) => mb_strlen($w) >= 2);

            if (count($words) > 2) {
                // phrase طويلة → خذ أول 2 كلمات
                $result[] = implode(' ', array_slice(array_values($words), 0, 2));
            } elseif (count($words) >= 1) {
                $result[] = $token;
            }
        }

        // dedup
        return array_values(array_unique(array_slice($result, 0, 5)));
    }

    private function sanitizeIntent(string $intent): string
    {
        $valid = ['buy', 'compare', 'learn', 'avoid', 'repair', 'general'];
        return in_array($intent, $valid, true) ? $intent : 'general';
    }

    private function buildFallback(string $query): array
    {
        return [
            'include'    => [],
            'exclude'    => [],
            'intent'     => 'general',
            'expanded'   => [],
            'corrected'  => $query,
            'confidence' => 0.0,
            'source'     => 'fallback',
        ];
    }
}