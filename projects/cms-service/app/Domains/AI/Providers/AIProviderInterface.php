<?php

namespace App\Domains\AI\Providers;

interface AIProviderInterface
{
  public function getName(): string;
  public function isAvailable(): bool;
  public function generate(string $systemPrompt, string $userPrompt): string;
}
