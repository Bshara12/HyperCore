<?php

namespace App\Domains\AI\Providers;

use Gemini;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AIProviderInterface
{
  private string $model;

  public function __construct()
  {
    $this->model = config('ai.providers.gemini.model', 'gemini-2.5-flash');
  }

  public function getName(): string
  {
    return 'Gemini';
  }

  public function isAvailable(): bool
  {
    return !empty(config('ai.providers.gemini.api_key'));
  }

  public function generate(string $systemPrompt, string $userPrompt): string
  {
    $client = Gemini::client(config('ai.providers.gemini.api_key'));

    $result = $client
      ->generativeModel(model: $this->model)
      ->generateContent([
        $systemPrompt,
        "User Request: " . $userPrompt,
      ]);

    $text = $result->text();

    if (empty($text)) {
      throw new \Exception("Gemini returned empty response.");
    }

    return $text;
  }
}
