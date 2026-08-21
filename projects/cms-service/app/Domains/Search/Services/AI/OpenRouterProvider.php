<?php

namespace App\Domains\Search\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenRouterProvider
 *
 * يستدعي OpenRouter API لتطبيع الـ search queries.
 * يُستخدم كـ fallback إذا فشل GeminiProvider.
 *
 * Endpoint:
 *   POST https://openrouter.ai/api/v1/chat/completions
 */
class OpenRouterProvider implements AIProviderInterface
{
    private const TIMEOUT          = 3;
    private const MAX_QUERY_LENGTH = 200;

    public function normalize(string $query, string $language): array
    {
        $apiKey   = config('services.openrouter.key');
        $model    = config('services.openrouter.model', 'mistralai/mistral-7b-instruct');
        $baseUrl  = config('services.openrouter.base_url', 'https://openrouter.ai/api/v1');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenRouterProvider: OPENROUTER_KEY not configured');
        }

        $sanitized = $this->sanitizeQuery($query);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ])
        ->timeout(self::TIMEOUT)
        ->post("{$baseUrl}/chat/completions", [
            'model'      => $model,
            'max_tokens' => 100,
            'temperature'=> 0.1,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $this->buildPrompt($sanitized, $language),
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "OpenRouterProvider: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $content = $response->json('choices.0.message.content');

        if (empty($content)) {
            throw new \RuntimeException('OpenRouterProvider: empty response content');
        }

        return $this->parseResponse($content, $query);
    }

    public function name(): string
    {
        return 'openrouter';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Prompt (نفس prompt الـ Gemini — consistency مهم)
    // ─────────────────────────────────────────────────────────────────────

    private function buildPrompt(string $query, string $language): string
    {
        $langHint = str_contains($language, 'ar')
            ? 'The query may be in Arabic or mixed Arabic-English.'
            : 'The query is in English.';

        return <<<PROMPT
You are a search query normalizer. {$langHint}

Return ONLY valid JSON, no markdown, no explanation.
Schema: {"normalized_query": "", "confidence": 0.0, "reasoning": ""}

Rules:
- Fix spelling (iphoen→iphone, samsng→samsung, laptp→laptop)
- Translate Arabic product names to English
- Remove filler words
- Do NOT invent model numbers or features not in the query
- max 5 words in normalized_query
- gibberish → normalized_query="" confidence=0.1

Examples:
"iphnoe 15" → {"normalized_query":"iphone 15","confidence":0.95,"reasoning":"spelling fix"}
"ايفون برو" → {"normalized_query":"iphone pro","confidence":0.92,"reasoning":"arabic translation"}
"لابتوب مونتاج" → {"normalized_query":"video editing laptop","confidence":0.85,"reasoning":"intent"}
"بدي تلفون للتصوير رخيص" → {"normalized_query":"budget camera phone","confidence":0.83,"reasoning":"extracted intent"}
"xzxzxz" → {"normalized_query":"","confidence":0.05,"reasoning":"gibberish"}

Query: "{$query}"
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Response Parsing
    // ─────────────────────────────────────────────────────────────────────

    private function parseResponse(string $content, string $originalQuery): array
    {
        $clean = preg_replace('/```(?:json)?\s*|\s*```/i', '', trim($content));
        $clean = trim($clean);

        if (preg_match('/\{[^{}]*\}/s', $clean, $match)) {
            $clean = $match[0];
        }

        $parsed = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            Log::warning('OpenRouterProvider: JSON parse failed', [
                'raw'   => substr($content, 0, 200),
                'error' => json_last_error_msg(),
            ]);
            throw new \RuntimeException('OpenRouterProvider: invalid JSON response');
        }

        return [
            'normalized_query' => trim($parsed['normalized_query'] ?? ''),
            'confidence'       => min(1.0, max(0.0, (float) ($parsed['confidence'] ?? 0.0))),
            'reasoning'        => trim($parsed['reasoning'] ?? ''),
        ];
    }

    private function sanitizeQuery(string $query): string
    {
        $query = mb_substr($query, 0, self::MAX_QUERY_LENGTH, 'UTF-8');
        $query = preg_replace('/```[\s\S]*?```/', '', $query);
        $query = preg_replace('/\b(ignore|forget|disregard)\s+(previous|above|all)\b/i', '', $query);

        return trim($query);
    }
}