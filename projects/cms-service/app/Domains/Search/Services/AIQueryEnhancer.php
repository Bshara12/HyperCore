<?php

namespace App\Domains\Search\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIQueryEnhancer
{
    private const CACHE_TTL_SECONDS = 3600;

    private const API_TIMEOUT = 8;

    private const MAX_TOKENS = 300;

    // ─── OpenRouter endpoint ──────────────────────────────────────────
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';

    // ─────────────────────────────────────────────────────────────────

    public function enhance(string $query, string $language): array
    {
        $cacheKey = $this->buildCacheKey($query, $language);

        // ─── Cache Check ──────────────────────────────────────────────
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            Log::debug('AIQueryEnhancer: cache hit', [
                'query' => $query,
                'language' => $language,
            ]);

            return array_merge($cached, ['source' => 'cache']);
        }

        // ─── استدعاء OpenRouter API ───────────────────────────────────
        Log::info('AIQueryEnhancer: calling OpenRouter API', [
            'query' => $query,
            'language' => $language,
            'model' => config('services.openrouter.model'),
        ]);

        try {
            $result = $this->callOpenRouterAPI($query, $language);

            Cache::put($cacheKey, $result, self::CACHE_TTL_SECONDS);

            Log::info('AIQueryEnhancer: enhancement successful', [
                'original' => $query,
                'corrected' => $result['correctedQuery'],
                'expanded_count' => count($result['expandedKeywords']),
                'confidence' => $result['confidence'],
            ]);

            return array_merge($result, ['source' => 'api']);

        } catch (\Throwable $e) {
            Log::warning('AIQueryEnhancer: API call failed, using fallback', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return $this->buildFallbackResult($query);
        }
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * استدعاء OpenRouter بصيغة OpenAI
     */
    private function callOpenRouterAPI(string $query, string $language): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openrouter.key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(self::API_TIMEOUT)
            ->post(self::API_URL, [
                'model' => config('services.openrouter.model', 'mistralai/mistral-7b-instruct'),
                'max_tokens' => self::MAX_TOKENS,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $this->buildPrompt($query, $language),
                    ],
                ],
            ]);

        // ─── تسجيل الـ error إذا فشل الطلب ───────────────────────────
        if (! $response->successful()) {
            Log::error('AIQueryEnhancer: OpenRouter API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query' => $query,
                'language' => $language,
            ]);

            throw new \RuntimeException(
                'OpenRouter API error: '.$response->status().' - '.$response->body()
            );
        }

        $data = $response->json();

        // ─── استخراج النص: choices[0].message.content ────────────────
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (empty($content)) {
            Log::error('AIQueryEnhancer: empty content from OpenRouter', [
                'full_response' => $data,
                'query' => $query,
            ]);

            throw new \RuntimeException('OpenRouter returned empty content');
        }

        return $this->parseAIResponse($content, $query);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * بناء الـ Prompt - لم يتغير
     */
    private function buildPrompt(string $query, string $language): string
    {
        $langName = match ($language) {
            'ar' => 'Arabic',
            'fr' => 'French',
            'de' => 'German',
            default => 'English',
        };

        return <<<PROMPT
You are a search query optimizer for a product/content search engine.

User query: "{$query}"
Language: {$langName}

Task: Return ONLY a valid JSON object with these fields:
- correctedQuery: the spell-corrected version of the query (string)
- expandedKeywords: array of 2-4 related/synonym search terms (string[])
- confidence: your confidence that corrections/expansions are accurate (0.0 to 1.0)

Rules:
1. If no corrections needed, correctedQuery = original query
2. expandedKeywords must be highly relevant, not generic
3. Respond ONLY with JSON, no explanation, no markdown

Example:
Input: "iphon prise"
Output: {"correctedQuery":"iphone price","expandedKeywords":["iphone cost","apple phone price","iphone deal"],"confidence":0.95}
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Parse الـ response - لم يتغير
     */
    private function parseAIResponse(string $content, string $originalQuery): array
    {
        $clean = preg_replace('/```(?:json)?\s*|\s*```/', '', trim($content));
        $clean = trim($clean);

        $parsed = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            Log::warning('AIQueryEnhancer: failed to parse JSON response', [
                'raw_content' => $content,
                'json_error' => json_last_error_msg(),
            ]);

            return $this->buildFallbackResult($originalQuery);
        }

        $correctedQuery = trim($parsed['correctedQuery'] ?? $originalQuery);
        $expandedKeywords = array_filter(
            array_map('trim', (array) ($parsed['expandedKeywords'] ?? [])),
            fn ($k) => ! empty($k) && mb_strlen($k) >= 2
        );
        $confidence = min(1.0, max(0.0, (float) ($parsed['confidence'] ?? 0.5)));

        $expandedKeywords = array_values(array_filter(
            $expandedKeywords,
            fn ($k) => mb_strtolower($k) !== mb_strtolower($correctedQuery)
        ));

        return [
            'correctedQuery' => $correctedQuery ?: $originalQuery,
            'expandedKeywords' => array_slice($expandedKeywords, 0, 4),
            'confidence' => $confidence,
            'originalQuery' => $originalQuery,
        ];
    }

    // ─────────────────────────────────────────────────────────────────

    private function buildFallbackResult(string $query): array
    {
        return [
            'correctedQuery' => $query,
            'expandedKeywords' => [],
            'confidence' => 0.0,
            'originalQuery' => $query,
            'source' => 'fallback',
        ];
    }

    private function buildCacheKey(string $query, string $language): string
    {
        return 'ai_enhance:'.md5(mb_strtolower(trim($query)).':'.$language);
    }
}
