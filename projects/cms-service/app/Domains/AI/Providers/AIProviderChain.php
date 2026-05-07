<?php

namespace App\Domains\AI\Providers;

use Illuminate\Support\Facades\Log;

class AIProviderChain
{
  /** @var AIProviderInterface[] */
  private array $providers;

  public function __construct(
    GeminiProvider     $gemini,
    OpenRouterProvider $openRouter,
  ) {
    $this->providers = [$gemini, $openRouter];
  }

  /**
   * يحاول كل provider بالترتيب حتى ينجح واحد
   */
  public function generate(string $systemPrompt, string $userPrompt): array
  {
    $errors  = [];
    $tried   = [];

    foreach ($this->providers as $provider) {

      $name = $provider->getName();

      // تحقق إن الـ provider متاح
      if (!$provider->isAvailable()) {
        Log::info("[AI Chain] {$name} skipped — not available (missing config).");
        $errors[$name] = 'not_available';
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

        $errorMsg = $e->getMessage();
        $errors[$name] = $errorMsg;

        Log::warning("[AI Chain] ❌ {$name} failed: {$errorMsg}");
        Log::warning("[AI Chain] Falling back to next provider...");
      }
    }

    // كل الـ providers فشلت
    $triedList = implode(' → ', $tried);
    Log::error("[AI Chain] All providers failed. Tried: {$triedList}");

    throw new \Exception(
      "All AI providers failed.\n" .
        collect($errors)->map(fn($err, $name) => "• {$name}: {$err}")->implode("\n")
    );
  }
}
