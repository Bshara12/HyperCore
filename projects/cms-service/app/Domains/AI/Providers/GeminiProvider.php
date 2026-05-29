<?php

namespace App\Domains\AI\Providers;

use Gemini;

class GeminiProvider implements AIProviderInterface
{
  private string $apiKey;
  private string $model;
  private string $label;
  private $client; // خاصية اختيارية

  // أضفنا $client = null في المعاملات
  public function __construct(string $apiKey, string $model, string $label = 'Gemini', $client = null)
  {
    $this->apiKey = $apiKey;
    $this->model = $model;
    $this->label = $label;
    $this->client = $client;
  }

  public function getName(): string
  {
    return $this->label;
  }

  public function isAvailable(): bool
  {
    return ! empty($this->apiKey);
  }

  public function generate(string $systemPrompt, string $userPrompt): string
  {
    // نستخدم العميل المحقون إذا وجد، وإلا نستخدم الـ Client الأصلي
    $client = $this->client ?? Gemini::client($this->apiKey);

    $result = $client
      ->generativeModel(model: $this->model)
      ->generateContent([
        $systemPrompt,
        'User Request: ' . $userPrompt,
      ]);

    $text = $result->text();

    if (empty($text)) {
      throw new \Exception("{$this->label} returned empty response.");
    }

    return $text;
  }
}
