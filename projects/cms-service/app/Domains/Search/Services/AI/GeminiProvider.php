<?php

namespace App\Domains\Search\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GeminiProvider
 *
 * يستدعي Google Gemini API لتطبيع الـ search queries.
 *
 * Endpoint:
 *   POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
 *
 * Output المطلوب من الـ model:
 *   {"normalized_query": "...", "confidence": 0.9, "reasoning": "..."}
 */
class GeminiProvider implements AIProviderInterface
{
    private const BASE_URL  = 'https://generativelanguage.googleapis.com/v1beta/models';
    private const TIMEOUT   = 3; // ثانية — سريع أو نتخلى عنه
    private const MAX_QUERY_LENGTH = 200; // حماية من prompt injection

    public function normalize(string $query, string $language): array
    {
        $apiKey = config('services.gemini.key');
        $model  = config('services.gemini.model', 'gemini-1.5-flash');

        if (empty($apiKey)) {
            throw new \RuntimeException('GeminiProvider: GEMINI_API_KEY not configured');
        }

        $sanitized = $this->sanitizeQuery($query);
        $prompt    = $this->buildPrompt($sanitized, $language);

        $response = Http::timeout(self::TIMEOUT)
            ->post(self::BASE_URL . "/{$model}:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature'     => 0.1,   // منخفض جداً لتجنب الإبداع
                    'maxOutputTokens' => 100,   // يمنع الإسهاب
                    'topP'            => 0.8,
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "GeminiProvider: HTTP {$response->status()} — {$response->body()}"
            );
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if (empty($content)) {
            throw new \RuntimeException('GeminiProvider: empty response content');
        }

        return $this->parseResponse($content, $query);
    }

    public function name(): string
    {
        return 'gemini';
    }

    // ─────────────────────────────────────────────────────────────────────
    // Prompt Building
    // ─────────────────────────────────────────────────────────────────────

    private function buildPrompt(string $query, string $language): string
    {
        $langHint = match (true) {
            str_contains($language, 'ar') => 'The query may be in Arabic or mixed Arabic-English.',
            default                       => 'The query is in English.',
        };

        return <<<PROMPT
You are a search query normalizer for a product/content search engine.

{$langHint}

Your ONLY job: rewrite the user's query into a clean, normalized English search phrase.

STRICT RULES:
1. Return ONLY valid JSON — no markdown, no explanation, no extra text.
2. normalized_query must use ONLY terms that can be derived from the user's input.
3. Do NOT invent product names, model numbers, or features not mentioned.
4. Do NOT add words the user didn't imply.
5. Translate Arabic product names to English equivalents.
6. Fix spelling mistakes.
7. Remove filler words (بدي, ودي, want, need, please, I want).
8. If query is pure gibberish → normalized_query="" and confidence=0.1
9. Keep it SHORT — max 5 words in normalized_query.
10. If unsure → return original terms as-is with low confidence.

JSON schema (return EXACTLY this):
{"normalized_query": "", "confidence": 0.0, "reasoning": ""}

Examples:
Input: "iphnoe 15"
Output: {"normalized_query": "iphone 15", "confidence": 0.95, "reasoning": "corrected spelling"}

Input: "ايفون برو ماكس"
Output: {"normalized_query": "iphone pro max", "confidence": 0.92, "reasoning": "translated arabic product name"}

Input: "لابتوب مونتاج"
Output: {"normalized_query": "video editing laptop", "confidence": 0.85, "reasoning": "interpreted arabic intent"}

Input: "سامسونغ جالكسي الترا"
Output: {"normalized_query": "samsung galaxy ultra", "confidence": 0.91, "reasoning": "translated and normalized"}

Input: "بدي تلفون منيح كتير للتصوير وما يكون غالي"
Output: {"normalized_query": "best camera phone budget", "confidence": 0.82, "reasoning": "extracted core intent from verbose query"}

Input: "شي للجامعة برمجة"
Output: {"normalized_query": "laptop programming student", "confidence": 0.78, "reasoning": "inferred use case"}

Input: "apple phone"
Output: {"normalized_query": "iphone", "confidence": 0.88, "reasoning": "normalized brand query"}

Input: "xkqpzmw"
Output: {"normalized_query": "", "confidence": 0.05, "reasoning": "gibberish input"}

Now normalize this query:
"{$query}"
PROMPT;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Response Parsing
    // ─────────────────────────────────────────────────────────────────────

    private function parseResponse(string $content, string $originalQuery): array
    {
        // إزالة markdown fences إذا وُجدت
        $clean = preg_replace('/```(?:json)?\s*|\s*```/i', '', trim($content));
        $clean = trim($clean);

        // استخراج أول JSON object
        if (preg_match('/\{[^{}]*\}/s', $clean, $match)) {
            $clean = $match[0];
        }

        $parsed = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($parsed)) {
            Log::warning('GeminiProvider: JSON parse failed', [
                'raw'     => substr($content, 0, 200),
                'error'   => json_last_error_msg(),
                'query'   => $originalQuery,
            ]);
            throw new \RuntimeException('GeminiProvider: invalid JSON response');
        }

        $normalizedQuery = trim($parsed['normalized_query'] ?? '');
        $confidence      = min(1.0, max(0.0, (float) ($parsed['confidence'] ?? 0.0)));
        $reasoning       = trim($parsed['reasoning'] ?? '');

        // Hallucination Guard: إذا الـ normalized query أطول بكثير من الأصل → نرفضه
        if ($this->looksLikeHallucination($normalizedQuery, $originalQuery)) {
            Log::warning('GeminiProvider: potential hallucination detected', [
                'original'   => $originalQuery,
                'normalized' => $normalizedQuery,
            ]);
            throw new \RuntimeException('GeminiProvider: hallucination detected');
        }

        return [
            'normalized_query' => $normalizedQuery,
            'confidence'       => $confidence,
            'reasoning'        => $reasoning,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Safety Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function sanitizeQuery(string $query): string
    {
        // حد أقصى للطول
        $query = mb_substr($query, 0, self::MAX_QUERY_LENGTH, 'UTF-8');

        // إزالة محاولات prompt injection الشائعة
        $query = preg_replace('/```[\s\S]*?```/', '', $query);
        $query = preg_replace('/\b(ignore|forget|disregard)\s+(previous|above|all)\b/i', '', $query);

        return trim($query);
    }

    /**
     * هل الـ normalized query تبدو كـ hallucination؟
     *
     * قاعدة بسيطة:
     *   إذا الناتج أطول بـ 3+ كلمات من المدخل
     *   AND المدخل لا يحتوي أياً من الكلمات الجديدة
     *   → محتمل hallucination
     */
    private function looksLikeHallucination(string $normalized, string $original): bool
    {
        if (empty($normalized)) {
            return false;
        }

        $normalizedWords = explode(' ', mb_strtolower($normalized, 'UTF-8'));
        $originalLower   = mb_strtolower($original, 'UTF-8');

        // إذا أكثر من 6 كلمات → مريب
        if (count($normalizedWords) > 6) {
            return true;
        }

        // إذا كلمات الناتج لا علاقة لها بالمدخل
        $newWords = 0;
        foreach ($normalizedWords as $word) {
            if (mb_strlen($word, 'UTF-8') >= 4 && ! str_contains($originalLower, $word)) {
                $newWords++;
            }
        }

        // إذا أكثر من 3 كلمات جديدة لم تكن في الأصل → مريب
        return $newWords > 3;
    }
}