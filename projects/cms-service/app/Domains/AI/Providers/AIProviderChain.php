<?php

namespace App\Domains\AI\Providers;

use Illuminate\Support\Facades\Log;

class AIProviderChain
{
  /** @var AIProviderInterface[] */
  private array $providers;

  public function __construct(
    OpenRouterProvider $openRouter
  ) {
    // ─── بناء الـ Gemini providers من الـ Config ──────────
    $geminiProviders = $this->buildGeminiProviders();

    // ─── ترتيب الأولوية ───────────────────────────────────
    $this->providers = [
      ...$geminiProviders, // Gemini Key 1, Key 2, ...
      $openRouter,         // OpenRouter
    ];
  }

  // ─────────────────────────────────────────────────────────
  // يبني Gemini providers من كل المفاتيح الموجودة في الـ Config
  // ─────────────────────────────────────────────────────────
  private function buildGeminiProviders(): array
  {
    $providers = [];
    $keys      = config('ai.providers.gemini.api_keys', []);
    $model     = config('ai.providers.gemini.model', 'gemini-2.5-flash');

    foreach ($keys as $index => $key) {
      if (empty($key)) continue;

      $label       = 'Gemini #' . ($index + 1);
      $providers[] = new GeminiProvider($key, $model, $label);
    }

    return $providers;
  }

  // ─────────────────────────────────────────────────────────
  // يحاول كل provider بالترتيب حتى ينجح واحد
  // ─────────────────────────────────────────────────────────
  public function generate(string $systemPrompt, string $userPrompt): array
  {
    $errors = [];
    $tried  = [];

    foreach ($this->providers as $provider) {

      $name = $provider->getName();

      if (!$provider->isAvailable()) {
        Log::info("[AI Chain] {$name} skipped — not available.");
        continue;
      }

      $tried[] = $name;

      try {
        Log::info("[AI Chain] Trying {$name}...");

        $rawText = $provider->generate($systemPrompt, $userPrompt);

        Log::info("[AI Chain] ✅ {$name} succeeded.");

        return [
          'text'     => $rawText,
          'provider' => $name,
          'errors'   => $errors,
        ];
      } catch (\Throwable $e) {

        $errors[$name] = $e->getMessage();
        Log::warning("[AI Chain] ❌ {$name} failed: {$e->getMessage()}");
        Log::warning("[AI Chain] Falling back to next provider...");
      }
    }

    $triedList = implode(' → ', $tried);
    Log::error("[AI Chain] All providers failed. Tried: {$triedList}");

    throw new \Exception(
      "All AI providers failed.\n" .
        collect($errors)
        ->map(fn($err, $name) => "• {$name}: {$err}")
        ->implode("\n")
    );
  }
}
