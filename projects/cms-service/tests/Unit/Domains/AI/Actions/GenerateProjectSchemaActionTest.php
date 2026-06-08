<?php

use App\Domains\AI\Actions\GenerateProjectSchemaAction;
use App\Domains\AI\Providers\AIProviderChain;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
  $this->chainMock = mock(AIProviderChain::class);
  $this->action = new GenerateProjectSchemaAction($this->chainMock);
});

test('it generates schema successfully from valid AI response', function () {
  // محاكاة استجابة الـ AI بداخل Markdown
  $rawResponse = '```json
    {
        "assistant_message": "Hello!",
        "schema": {"key": "value"}
    }
    ```';

  $this->chainMock->shouldReceive('generate')
    ->once()
    ->andReturn([
      'text' => $rawResponse,
      'provider' => 'openai',
      'errors' => []
    ]);

  // التأكد من أن الـ Log يتم استدعاؤه
  Log::shouldReceive('info')->once();

  $result = $this->action->execute('Create a CRM');

  expect($result['success'])->toBeTrue()
    ->and($result['provider_used'])->toBe('openai')
    ->and($result['ai_chat_bubble'])->toBe('Hello!')
    ->and($result['technical_schema'])->toBe(['key' => 'value']);
});

test('it handles provider errors and logs warning', function () {
  $this->chainMock->shouldReceive('generate')
    ->andReturn([
      'text' => '{"assistant_message": "OK"}',
      'provider' => 'claude',
      'errors' => ['openai' => 'failed'] // محاكاة فشل provider
    ]);

  Log::shouldReceive('info')->once();
  Log::shouldReceive('warning')->once()->with(Mockery::pattern('/Failed providers: openai/'));

  $result = $this->action->execute('Create a CRM');

  expect($result['success'])->toBeTrue();
});

test('it throws exception when all providers fail', function () {
  $this->chainMock->shouldReceive('generate')
    ->andThrow(new \Exception('All providers failed'));

  Log::shouldReceive('error')->once();

  $this->action->execute('Create a CRM');
})->throws(\Exception::class, 'All providers failed');

test('it builds prompt with history correctly and covers private method', function () {
  $history = [
    ['role' => 'user', 'content' => 'Hello'],
    ['role' => 'assistant', 'content' => 'Hi', 'schema' => ['id' => 123]],
  ];

  // استخدم andReturnUsing بدلاً من with
  $this->chainMock->shouldReceive('generate')
    ->once()
    ->andReturnUsing(function ($systemPrompt, $fullPrompt) {
      expect($fullPrompt)->toContain('=== CONVERSATION HISTORY ===')
        ->and($fullPrompt)->toContain('[USER]:')
        ->and($fullPrompt)->toContain('[ASSISTANT]:')
        // ابحث عن أجزاء الـ JSON بشكل منفصل لتجنب مشاكل المسافات والأسطر
        ->and($fullPrompt)->toContain('"id":')
        ->and($fullPrompt)->toContain('123');

      return [
        'text' => '{"assistant_message": "OK"}',
        'provider' => 'openai',
        'errors' => []
      ];
    });

  $this->action->execute('New request', $history);
});
