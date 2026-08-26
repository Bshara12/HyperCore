<?php

namespace App\Domains\Search\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenRouterProvider — Fixed
 *
 * الإصلاحات:
 *   1. يقرأ config('services.openrouter.key') — موجود في services.php ✓
 *   2. output يحتوي negative_terms مثل GeminiProvider
 *   3. timeout = 5 ثواني
 *   4. Prompt محدَّث مطابق لـ GeminiProvider
 */
class OpenRouterProvider implements AIProviderInterface
{
    private const TIMEOUT = 5;

    private const MAX_QUERY_LEN = 300;

    public function normalize(string $query, string $language): array
    {
        $apiKey = config('services.openrouter.key');
        $model = config('services.openrouter.model', 'mistralai/mistral-7b-instruct');
        $baseUrl = config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenRouterProvider: OPENROUTER_API_KEY not configured (services.openrouter.key)');
        }

        $sanitized = $this->sanitizeQuery($query);
        $startTime = microtime(true);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
        ])
            ->timeout(self::TIMEOUT)
            ->post("{$baseUrl}/chat/completions", [
                'model' => $model,
                'max_tokens' => 200,
                'temperature' => 0.1,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $this->buildPrompt($sanitized, $language),
                    ],
                ],
            ]);

        $elapsedMs = round((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            Log::warning('OpenRouterProvider: HTTP error', [
                'status' => $response->status(),
                'elapsed_ms' => $elapsedMs,
                'query' => $query,
            ]);
            throw new \RuntimeException("OpenRouterProvider: HTTP {$response->status()}");
        }

        $content = $response->json('choices.0.message.content');

        if (empty($content)) {
            throw new \RuntimeException('OpenRouterProvider: empty response');
        }

        $result = $this->parseResponse($content, $query);

        Log::info('OpenRouterProvider: success', [
            'provider' => 'openrouter',
            'query' => $query,
            'normalized_query' => $result['normalized_query'],
            'negative_terms' => $result['negative_terms'],
            'confidence' => $result['confidence'],
            'elapsed_ms' => $elapsedMs,
        ]);

        return $result;
    }

    public function name(): string
    {
        return 'openrouter';
    }

    private function buildPrompt(string $query, string $language): string
    {
        $langHint = str_contains($language, 'ar')
            ? 'Input may be Arabic, mixed Arabic-English, or Arabic dialect.'
            : 'Input is in English or mixed language.';

        return <<<PROMPT
You are a multilingual search query normalizer. {$langHint}

Return ONLY valid JSON. No markdown. No explanation.
Schema: {"normalized_query":"","negative_terms":[],"confidence":0.0,"reasoning":""}

Rules:
- Translate Arabic to English (ايفون→iphone, لابتوب→laptop, سامسونج→samsung)
- Fix spelling (iphoen→iphone, samsng→samsung, laptp→laptop)
- Remove fillers (بدي, ودي, want, need, please, يلي, كل)
- Keep year numbers (2020, 2021, etc.)
- Negation → negative_terms (لا, ما, don't, not, without)
- Intent queries (مونتاج→video editing, برمجة→programming)
- Max 6 words in normalized_query
- Gibberish → normalized_query="" confidence=0.05

Examples:
"ايفون برو ماكس" → {"normalized_query":"iphone pro max","negative_terms":[],"confidence":0.95,"reasoning":"arabic translation"}
"لابتوب مونتاج فيديو" → {"normalized_query":"video editing laptop","negative_terms":[],"confidence":0.90,"reasoning":"intent"}
"بدي الايفون يلي نزل بال 2021" → {"normalized_query":"iphone 2021","negative_terms":[],"confidence":0.85,"reasoning":"year query"}
"بدي كل الايفونات من 2020 لحد الان" → {"normalized_query":"iphone 2020 2021 2022 2023 2024","negative_terms":[],"confidence":0.82,"reasoning":"date range"}
"i dont need iphone 14" → {"normalized_query":"iphone","negative_terms":["14"],"confidence":0.92,"reasoning":"negation"}
"apple 14" → {"normalized_query":"iphone 14","negative_terms":[],"confidence":0.93,"reasoning":"apple=iphone"}
"iphnoe 15" → {"normalized_query":"iphone 15","negative_terms":[],"confidence":0.95,"reasoning":"spelling fix"}
"xzxzxz" → {"normalized_query":"","negative_terms":[],"confidence":0.04,"reasoning":"gibberish"}

Query: "{$query}"
PROMPT;
    }

    private function parseResponse(string $content, string $originalQuery): array
    {
        $clean = preg_replace('/```(?:json)?\s*|\s*```/i', '', trim($content));
        $clean = trim($clean);

        if (preg_match('/\{.*\}/s', $clean, $match)) {
            $clean = $match[0];
        }

        $parsed = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            Log::warning('OpenRouterProvider: JSON parse failed', [
                'raw' => substr($content, 0, 300),
                'error' => json_last_error_msg(),
                'query' => $originalQuery,
            ]);
            throw new \RuntimeException('OpenRouterProvider: invalid JSON response');
        }

        return [
            'normalized_query' => trim($parsed['normalized_query'] ?? ''),
            'negative_terms' => $this->sanitizeStringArray($parsed['negative_terms'] ?? []),
            'confidence' => min(1.0, max(0.0, (float) ($parsed['confidence'] ?? 0.0))),
            'reasoning' => trim($parsed['reasoning'] ?? ''),
        ];
    }

    private function sanitizeQuery(string $query): string
    {
        $query = mb_substr($query, 0, self::MAX_QUERY_LEN, 'UTF-8');
        $query = preg_replace('/```[\s\S]*?```/', '', $query);
        $query = preg_replace('/\b(ignore|forget|disregard)\s+(previous|above|all)\b/i', '', $query);

        return trim($query);
    }

    private function sanitizeStringArray(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($v) => is_string($v) ? trim(mb_strtolower($v, 'UTF-8')) : null, $input),
            fn ($v) => $v !== null && $v !== ''
        ));
    }
}
