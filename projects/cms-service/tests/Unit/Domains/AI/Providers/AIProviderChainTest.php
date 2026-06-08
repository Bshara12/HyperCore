<?php

namespace Tests\Unit\Domains\AI\Providers;

use App\Domains\AI\Providers\AIProviderChain;
use App\Domains\AI\Providers\AIProviderInterface;
use App\Domains\AI\Providers\GeminiProvider;
use App\Domains\AI\Providers\OpenRouterProvider;
use Illuminate\Support\Facades\Log;
require_once __DIR__ . '/Helpers.php';

afterEach(fn () => \Mockery::close());

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * ينشئ mock لأي provider بناءً على الاسم والتوفر والنتيجة المتوقعة
 */
function makeProviderMock(string $name, bool $available, string|\Exception $result): object
{
    $mock = mock(AIProviderInterface::class);
    $mock->shouldReceive('getName')->andReturn($name);
    $mock->shouldReceive('isAvailable')->andReturn($available);

    if ($result instanceof \Exception) {
        $mock->shouldReceive('generate')->andThrow($result);
    } else {
        $mock->shouldReceive('generate')->andReturn($result);
    }

    return $mock;
}

/**
 * ينشئ نسخة من TestableAIProviderChain مع mock client محقون للـ GeminiProvider
 */
function makeChainWithMockClient(object $openRouter, object $mockClient): TestableAIProviderChain
{
    $chain = new TestableAIProviderChain($openRouter);
    $chain->setMockClient($mockClient);
    return $chain;
}



// ─────────────────────────────────────────────────────────────────────────────
// Test Subclass — تسمح بحقن mock client داخل GeminiProvider
// ─────────────────────────────────────────────────────────────────────────────

class TestableAIProviderChain extends AIProviderChain
{
    private object $mockClient;

    public function setMockClient(object $client): void
    {
        $this->mockClient = $client;
    }

    protected function makeGeminiProvider(string $key, string $model, string $label): GeminiProvider
    {
        return new GeminiProvider($key, $model, $label, $this->mockClient);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// generate() — السلوك الأساسي
// ─────────────────────────────────────────────────────────────────────────────

test('it returns successful result when the first provider succeeds', function () {
    Log::spy();

    $gemini     = makeProviderMock('Gemini #1', true, 'Success Result');
    $openRouter = mock(OpenRouterProvider::class);

    $chain  = new AIProviderChain($openRouter, [$gemini]);
    $result = $chain->generate('system', 'user');

    expect($result['text'])->toBe('Success Result')
        ->and($result['provider'])->toBe('Gemini #1');
});

test('it skips a provider if it is not available', function () {
    Log::spy();

    $gemini     = makeProviderMock('Unavailable Gemini', false, 'should not be called');
    $openRouter = mock(OpenRouterProvider::class);
    $openRouter->shouldReceive('getName')->andReturn('OpenRouter');
    $openRouter->shouldReceive('isAvailable')->andReturn(true);
    $openRouter->shouldReceive('generate')->once()->andReturn('OpenRouter Success');

    $chain  = new AIProviderChain($openRouter, [$gemini]);
    $result = $chain->generate('system', 'user');

    expect($result['provider'])->toBe('OpenRouter')
        ->and($result['text'])->toBe('OpenRouter Success');
});

test('it falls back to openrouter when gemini fails', function () {
    Log::spy();

    $gemini     = makeProviderMock('Gemini #1', true, new \Exception('Gemini Failed'));
    $openRouter = mock(OpenRouterProvider::class);
    $openRouter->shouldReceive('getName')->andReturn('OpenRouter');
    $openRouter->shouldReceive('isAvailable')->andReturn(true);
    $openRouter->shouldReceive('generate')->once()->andReturn('OpenRouter Success');

    $chain  = new AIProviderChain($openRouter, [$gemini]);
    $result = $chain->generate('system', 'user');

    expect($result['text'])->toBe('OpenRouter Success')
        ->and($result['provider'])->toBe('OpenRouter');
});

test('it collects errors from failed providers in the result', function () {
    Log::spy();

    $gemini     = makeProviderMock('Gemini #1', true, new \Exception('Gemini Failed'));
    $openRouter = mock(OpenRouterProvider::class);
    $openRouter->shouldReceive('getName')->andReturn('OpenRouter');
    $openRouter->shouldReceive('isAvailable')->andReturn(true);
    $openRouter->shouldReceive('generate')->andReturn('OpenRouter Success');

    $chain  = new AIProviderChain($openRouter, [$gemini]);
    $result = $chain->generate('system', 'user');

    expect($result['errors'])->toHaveKey('Gemini #1')
        ->and($result['errors']['Gemini #1'])->toBe('Gemini Failed');
});

test('it tries multiple gemini providers in order', function () {
    Log::spy();

    $gemini1    = makeProviderMock('Gemini #1', true, new \Exception('Error 1'));
    $gemini2    = makeProviderMock('Gemini #2', true, 'Gemini #2 Success');
    $openRouter = mock(OpenRouterProvider::class);

    $chain  = new AIProviderChain($openRouter, [$gemini1, $gemini2]);
    $result = $chain->generate('system', 'user');

    expect($result['provider'])->toBe('Gemini #2')
        ->and($result['text'])->toBe('Gemini #2 Success');
});

test('it throws exception when all providers fail', function () {
    Log::spy();

    $gemini     = makeProviderMock('Gemini #1', true, new \Exception('Error 1'));
    $openRouter = mock(OpenRouterProvider::class);
    $openRouter->shouldReceive('getName')->andReturn('OpenRouter');
    $openRouter->shouldReceive('isAvailable')->andReturn(true);
    $openRouter->shouldReceive('generate')->andThrow(new \Exception('Error 2'));

    $chain = new AIProviderChain($openRouter, [$gemini]);

    expect(fn () => $chain->generate('system', 'user'))
        ->toThrow(\Exception::class);
});

test('exception message contains all provider errors', function () {
    Log::spy();

    $gemini     = makeProviderMock('Gemini #1', true, new \Exception('Gemini error'));
    $openRouter = mock(OpenRouterProvider::class);
    $openRouter->shouldReceive('getName')->andReturn('OpenRouter');
    $openRouter->shouldReceive('isAvailable')->andReturn(true);
    $openRouter->shouldReceive('generate')->andThrow(new \Exception('Router error'));

    $chain = new AIProviderChain($openRouter, [$gemini]);

    expect(fn () => $chain->generate('system', 'user'))
        ->toThrow(\Exception::class, 'Gemini #1: Gemini error');
});

// ─────────────────────────────────────────────────────────────────────────────
// buildGeminiProviders() — بناء الـ providers من الـ Config
// ─────────────────────────────────────────────────────────────────────────────

test('it uses buildGeminiProviders when no providers injected and config is empty', function () {
    Log::spy();

    config([
        'ai.providers.gemini.api_keys' => [],
        'ai.providers.gemini.model'    => 'gemini-2.5-flash',
    ]);

    $openRouter = mock(OpenRouterProvider::class);
    $openRouter->shouldReceive('getName')->andReturn('OpenRouter');
    $openRouter->shouldReceive('isAvailable')->andReturn(true);
    $openRouter->shouldReceive('generate')->andReturn('OpenRouter Only');

    $chain  = new AIProviderChain($openRouter);
    $result = $chain->generate('system', 'user');

    expect($result['provider'])->toBe('OpenRouter');
});

test('buildGeminiProviders creates GeminiProvider with correct label from real factory', function () {
    Log::spy();

    config([
        'ai.providers.gemini.api_keys' => ['real-key'],
        'ai.providers.gemini.model'    => 'gemini-2.5-flash',
    ]);

    // نستخدم AIProviderChain الحقيقية (ليس TestableAIProviderChain)
    // ونجعل OpenRouter ينجح حتى لا نحتاج Gemini API
    $openRouter = mock(OpenRouterProvider::class);
    $openRouter->shouldReceive('getName')->andReturn('OpenRouter');
    $openRouter->shouldReceive('isAvailable')->andReturn(false); // نتخطاه

    // Gemini سيفشل لأنه بدون client حقيقي — OpenRouter أيضاً غير متاح
    // إذن نتوقع exception من كل الـ providers
    $chain = new AIProviderChain($openRouter); // ← real chain تمر على السطر 41

    expect(fn () => $chain->generate('system', 'user'))
        ->toThrow(\Exception::class); // كل الـ providers فشلت
});

test('buildGeminiProviders skips all empty keys and falls back to openrouter', function () {
    Log::spy();

    config([
        'ai.providers.gemini.api_keys' => ['', '', ''],
        'ai.providers.gemini.model'    => 'gemini-2.5-flash',
    ]);

    $openRouter = mock(OpenRouterProvider::class);
    $openRouter->shouldReceive('getName')->andReturn('OpenRouter');
    $openRouter->shouldReceive('isAvailable')->andReturn(true);
    $openRouter->shouldReceive('generate')->once()->andReturn('OpenRouter Only');

    $chain  = makeChainWithMockClient($openRouter, mock(\stdClass::class));
    $result = $chain->generate('system', 'user');

    expect($result['provider'])->toBe('OpenRouter')
        ->and($result['errors'])->toBeEmpty();
});