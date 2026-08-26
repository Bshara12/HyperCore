<?php

namespace App\Domains\Search\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    private const TIMEOUT = 5;

    private const MAX_QUERY_LEN = 300;

    public function normalize(string $query, string $language): array
    {
        // FIX: api_key وليس key
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (empty($apiKey)) {
            throw new \RuntimeException('GeminiProvider: GEMINI_API_KEY not set');
        }

        $sanitized = $this->sanitizeQuery($query);
        $startTime = microtime(true);

        $response = Http::timeout(self::TIMEOUT)
            ->post(self::BASE_URL."/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    ['parts' => [['text' => $this->buildPrompt($sanitized, $language)]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.1,
                    'maxOutputTokens' => 200,
                    'topP' => 0.8,
                ],
            ]);

        $elapsedMs = round((microtime(true) - $startTime) * 1000);

        if (! $response->successful()) {
            Log::warning('GeminiProvider: HTTP error', [
                'status' => $response->status(),
                'body' => $response->body(), // ← يكشف السبب الحقيقي من Google (model not found, invalid key, etc.)
                'elapsed_ms' => $elapsedMs,
                'query' => $query,
            ]);
            throw new \RuntimeException("GeminiProvider: HTTP {$response->status()} - {$response->body()}");
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if (empty($content)) {
            throw new \RuntimeException('GeminiProvider: empty response');
        }

        $result = $this->parseResponse($content, $query);

        Log::info('GeminiProvider: success', [
            'provider' => 'gemini',
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
        return 'gemini';
    }

    private function buildPrompt(string $query, string $language): string
    {
        $langHint = str_contains($language, 'ar')
            ? 'Input may be Arabic, mixed Arabic-English, or Arabic dialect (Levantine, Gulf, Egyptian).'
            : 'Input is in English or mixed language.';

        return <<<PROMPT
You are a multilingual search query normalizer for a product/content search engine.

{$langHint}

TASK: Convert the user's raw input into a structured search intent.

STRICT RULES:
1. Return ONLY valid JSON. No markdown. No explanation. No extra text.
2. Translate Arabic words to English equivalents.
3. Fix spelling (iphoen→iphone, samsng→samsung, laptp→laptop).
4. Remove filler words: بدي, ودي, اريد, عايز, want, need, please, show me, give me, يلي, كل.
5. For date/year queries: keep the year numbers in normalized_query.
6. For negation (لا, ما, don't, dont, not, without): put negated terms in negative_terms array.
7. For intent queries (مونتاج→video editing, برمجة→programming): convert to English use-case.
8. normalized_query max 6 words.
9. Gibberish → normalized_query="" confidence=0.05
10. Do NOT invent model numbers not implied by the query.

JSON Schema (return EXACTLY this):
{"normalized_query":"","negative_terms":[],"confidence":0.0,"reasoning":""}

EXAMPLES:
"ايفون برو ماكس" → {"normalized_query":"iphone pro max","negative_terms":[],"confidence":0.95,"reasoning":"arabic translation"}
"لابتوب مونتاج فيديو" → {"normalized_query":"video editing laptop","negative_terms":[],"confidence":0.90,"reasoning":"intent: video editing"}
"بدي الايفون يلي نزل بال 2021" → {"normalized_query":"iphone 2021","negative_terms":[],"confidence":0.85,"reasoning":"year query"}
"بدي كل الايفونات من 2020 لحد الان" → {"normalized_query":"iphone 2020 2021 2022 2023 2024","negative_terms":[],"confidence":0.82,"reasoning":"date range"}
"i dont need iphone 14" → {"normalized_query":"iphone","negative_terms":["14"],"confidence":0.92,"reasoning":"negation: exclude 14"}
"apple 14" → {"normalized_query":"iphone 14","negative_terms":[],"confidence":0.93,"reasoning":"apple+number=iphone model"}
"samsung phone under 500" → {"normalized_query":"samsung phone budget","negative_terms":[],"confidence":0.88,"reasoning":"price constraint normalized"}
"best gaming laptop for editing" → {"normalized_query":"gaming laptop video editing","negative_terms":[],"confidence":0.91,"reasoning":"dual use case"}
"iphnoe 15" → {"normalized_query":"iphone 15","negative_terms":[],"confidence":0.96,"reasoning":"spelling fix"}
"xkqpzmw" → {"normalized_query":"","negative_terms":[],"confidence":0.04,"reasoning":"gibberish"}

Now process:
"{$query}"
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
            Log::warning('GeminiProvider: JSON parse failed', [
                'raw' => substr($content, 0, 300),
                'error' => json_last_error_msg(),
                'query' => $originalQuery,
            ]);
            throw new \RuntimeException('GeminiProvider: invalid JSON response');
        }

        $normalizedQuery = trim($parsed['normalized_query'] ?? '');
        $confidence = min(1.0, max(0.0, (float) ($parsed['confidence'] ?? 0.0)));
        $negativeTerms = $this->sanitizeArray($parsed['negative_terms'] ?? []);
        $reasoning = trim($parsed['reasoning'] ?? '');

        // FIX: Hallucination Guard مُخفَّف للـ Arabic (الترجمة طبيعية)
        if ($this->looksLikeHallucination($normalizedQuery, $originalQuery)) {
            Log::warning('GeminiProvider: hallucination detected', [
                'original' => $originalQuery,
                'normalized' => $normalizedQuery,
            ]);
            throw new \RuntimeException('GeminiProvider: hallucination detected');
        }

        return [
            'normalized_query' => $normalizedQuery,
            'negative_terms' => $negativeTerms,
            'confidence' => $confidence,
            'reasoning' => $reasoning,
        ];
    }

    private function looksLikeHallucination(string $normalized, string $original): bool
    {
        if (empty($normalized)) {
            return false;
        }

        $words = array_filter(explode(' ', mb_strtolower($normalized, 'UTF-8')));

        // أكثر من 7 كلمات دائماً مريب
        if (count($words) > 7) {
            return true;
        }

        // إذا query عربي → الترجمة طبيعية → لا hallucination check
        $arabicCount = preg_match_all('/[\x{0600}-\x{06FF}]/u', $original);
        $totalChars = mb_strlen(preg_replace('/\s/', '', $original), 'UTF-8');
        if ($totalChars > 0 && ($arabicCount / $totalChars) > 0.2) {
            return false;
        }

        // Query إنجليزي → نتحقق من كلمات جديدة كلياً
        $originalLower = mb_strtolower($original, 'UTF-8');
        $newWords = 0;

        foreach ($words as $word) {
            if (mb_strlen($word, 'UTF-8') >= 4 && ! str_contains($originalLower, $word)) {
                $newWords++;
            }
        }

        return $newWords > 4;
    }

    private function sanitizeQuery(string $query): string
    {
        $query = mb_substr($query, 0, self::MAX_QUERY_LEN, 'UTF-8');
        $query = preg_replace('/```[\s\S]*?```/', '', $query);
        $query = preg_replace('/\b(ignore|forget|disregard)\s+(previous|above|all)\b/i', '', $query);

        return trim($query);
    }

    private function sanitizeArray(mixed $input): array
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
