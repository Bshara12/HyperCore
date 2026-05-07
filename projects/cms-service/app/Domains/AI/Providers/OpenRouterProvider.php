<?php

namespace App\Domains\AI\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterProvider implements AIProviderInterface
{
  private string $apiKey;
  private string $baseUrl;

  // ترتيب الموديلات — لو الأول فشل نجرب الثاني
  private array $modelFallbacks = [
    'openrouter/free',                          // تلقائي — الأفضل
    'meta-llama/llama-3.3-70b-instruct:free',  // Llama 3.3
    'deepseek/deepseek-r1:free',               // DeepSeek R1
    'qwen/qwen3-8b:free',                      // Qwen3
    'mistralai/mistral-7b-instruct:free',      // Mistral
  ];

  public function __construct()
  {
    $this->apiKey  = config('ai.providers.openrouter.api_key', '');
    $this->baseUrl = config('ai.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');

    // لو المستخدم حدد موديل في .env — ضعه أول الـ list
    $configModel = config('ai.providers.openrouter.model');
    if ($configModel && $configModel !== 'openrouter/free') {
      array_unshift($this->modelFallbacks, $configModel);
      $this->modelFallbacks = array_unique($this->modelFallbacks);
    }
  }

  public function getName(): string
  {
    return 'OpenRouter';
  }

  public function isAvailable(): bool
  {
    return !empty($this->apiKey);
  }

  public function generate(string $systemPrompt, string $userPrompt): string
  {
    $lastError = null;

    foreach ($this->modelFallbacks as $model) {
      try {
        Log::info("[OpenRouter] Trying model: {$model}");

        $text = $this->callApi($model, $systemPrompt, $userPrompt);

        Log::info("[OpenRouter] ✅ Success with model: {$model}");
        return $text;
      } catch (\Throwable $e) {
        $lastError = $e->getMessage();
        Log::warning("[OpenRouter] ❌ Model {$model} failed: {$lastError}");

        // لو مش 404 (يعني مشكلة غير موديل) — وقف فوراً
        if (!str_contains($lastError, '404') && !str_contains($lastError, 'No endpoints')) {
          throw $e;
        }

        // 404 = الموديل مش متاح — جرب الثاني
        continue;
      }
    }

    throw new \Exception("OpenRouter: All models failed. Last error: {$lastError}");
  }

  // ─────────────────────────────────────────────────────────
  private function callApi(string $model, string $systemPrompt, string $userPrompt): string
  {
    $response = Http::withHeaders([
      'Authorization' => "Bearer {$this->apiKey}",
      'Content-Type'  => 'application/json',
      'HTTP-Referer'  => config('app.url', 'http://localhost'),
      'X-Title'       => config('app.name', 'HyperCore'),
    ])
      ->timeout(180) // زيادة الوقت لـ 3 دقائق
      ->connectTimeout(10)
      ->post("{$this->baseUrl}/chat/completions", [
        'model'    => $model,
        'messages' => [
          ['role' => 'system', 'content' => $systemPrompt],
          ['role' => 'user',   'content' => $userPrompt],
        ],
        'temperature' => 0.7,
        'max_tokens'  => 3000,
      ]);

    if ($response->status() === 404) {
      throw new \Exception("No endpoints found for {$model}. (404)");
    }

    if (!$response->successful()) {
      throw new \Exception(
        "OpenRouter error: {$response->status()} — {$response->body()}"
      );
    }

    $text = $response->json('choices.0.message.content');

    if (empty($text)) {
      throw new \Exception("OpenRouter returned empty response for model: {$model}");
    }

    return $text;
  }
}
