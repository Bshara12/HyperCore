<?php

namespace Tests\Unit\Domains\AI\Providers;

use App\Domains\AI\Providers\OpenRouterProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

afterEach(fn() => \Mockery::close());

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * يضبط الـ config الأساسي للـ OpenRouterProvider
 */
function setOpenRouterConfig(string $apiKey = 'test-api-key', ?string $model = null): void
{
  config([
    'ai.providers.openrouter.api_key'  => $apiKey,
    'ai.providers.openrouter.base_url' => 'https://openrouter.ai/api/v1',
    'ai.providers.openrouter.model'    => $model,
    'app.url'                          => 'http://localhost',
    'app.name'                         => 'TestApp',
  ]);
}

/**
 * يرجع array الـ response الناجح — يُستخدم داخل Http::fake() فقط
 */
function successBody(string $text = 'AI Response'): array
{
  return [
    'choices' => [
      ['message' => ['content' => $text]],
    ],
  ];
}

// ─────────────────────────────────────────────────────────────────────────────
// getName()
// ─────────────────────────────────────────────────────────────────────────────

test('getName returns OpenRouter', function () {
  setOpenRouterConfig();

  $provider = new OpenRouterProvider();

  expect($provider->getName())->toBe('OpenRouter');
});

// ─────────────────────────────────────────────────────────────────────────────
// isAvailable()
// ─────────────────────────────────────────────────────────────────────────────

test('isAvailable returns true when api key is set', function () {
  setOpenRouterConfig('valid-key');

  $provider = new OpenRouterProvider();

  expect($provider->isAvailable())->toBeTrue();
});

test('isAvailable returns false when api key is empty', function () {
  setOpenRouterConfig('');

  $provider = new OpenRouterProvider();

  expect($provider->isAvailable())->toBeFalse();
});

// ─────────────────────────────────────────────────────────────────────────────
// Constructor — Model Fallbacks
// ─────────────────────────────────────────────────────────────────────────────

test('constructor prepends custom model from config to fallbacks', function () {
  Log::spy();
  setOpenRouterConfig('key', 'custom/model');

  Http::fake(['*' => Http::response(successBody('ok'), 200)]);

  $provider = new OpenRouterProvider();
  $provider->generate('system', 'user');

  Http::assertSent(function ($request) {
    return $request['model'] === 'custom/model';
  });
});

test('constructor does not prepend model if it equals openrouter/free', function () {
  Log::spy();
  setOpenRouterConfig('key', 'openrouter/free');

  Http::fake(['*' => Http::response(successBody('ok'), 200)]);

  $provider = new OpenRouterProvider();
  $provider->generate('system', 'user');

  // openrouter/free موجود أصلاً — لا يتكرر، طلب واحد فقط
  Http::assertSentCount(1);
});

test('constructor does not prepend model if config model is null', function () {
  Log::spy();
  setOpenRouterConfig('key', null);

  Http::fake(['*' => Http::response(successBody('ok'), 200)]);

  $provider = new OpenRouterProvider();
  $provider->generate('system', 'user');

  // يبدأ بالـ default: openrouter/free
  Http::assertSent(function ($request) {
    return $request['model'] === 'openrouter/free';
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// generate() — Happy Path
// ─────────────────────────────────────────────────────────────────────────────

test('generate returns text from successful api response', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fake(['*' => Http::response(successBody('Hello from OpenRouter!'), 200)]);

  $provider = new OpenRouterProvider();
  $result   = $provider->generate('System prompt', 'User prompt');

  expect($result)->toBe('Hello from OpenRouter!');
});

test('generate sends correct headers to the api', function () {
  Log::spy();
  setOpenRouterConfig('my-secret-key');

  Http::fake(['*' => Http::response(successBody('ok'), 200)]);

  $provider = new OpenRouterProvider();
  $provider->generate('system', 'user');

  Http::assertSent(function ($request) {
    return $request->hasHeader('Authorization', 'Bearer my-secret-key')
      && $request->hasHeader('Content-Type', 'application/json')
      && $request->hasHeader('HTTP-Referer', 'http://localhost')
      && $request->hasHeader('X-Title', 'TestApp');
  });
});

test('generate sends correct messages structure to the api', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fake(['*' => Http::response(successBody('ok'), 200)]);

  $provider = new OpenRouterProvider();
  $provider->generate('Be helpful', 'What is PHP?');

  Http::assertSent(function ($request) {
    $messages = $request['messages'];

    return $messages[0]['role'] === 'system'
      && $messages[0]['content'] === 'Be helpful'
      && $messages[1]['role'] === 'user'
      && $messages[1]['content'] === 'What is PHP?';
  });
});

test('generate sends correct endpoint url', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fake(['*' => Http::response(successBody('ok'), 200)]);

  $provider = new OpenRouterProvider();
  $provider->generate('system', 'user');

  Http::assertSent(function ($request) {
    return str_contains($request->url(), '/chat/completions');
  });
});

// ─────────────────────────────────────────────────────────────────────────────
// generate() — 404 Fallback
// ─────────────────────────────────────────────────────────────────────────────

test('generate falls back to next model when first returns 404', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fakeSequence()
    ->push(['error' => 'not found'], 404)
    ->push(successBody('Fallback Success'), 200);

  $provider = new OpenRouterProvider();
  $result   = $provider->generate('system', 'user');

  expect($result)->toBe('Fallback Success');
});

test('generate tries all models before giving up on 404s', function () {
  Log::spy();
  setOpenRouterConfig();

  // كل الـ requests ترجع 404
  Http::fake(['*' => Http::response(['error' => 'not found'], 404)]);

  $provider = new OpenRouterProvider();

  expect(fn() => $provider->generate('system', 'user'))
    ->toThrow(\Exception::class, 'All models failed');
});

// ─────────────────────────────────────────────────────────────────────────────
// generate() — Non-404 Errors (يوقف فوراً)
// ─────────────────────────────────────────────────────────────────────────────

test('generate throws immediately on non-404 http error', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fake(['*' => Http::response('Internal Server Error', 500)]);

  $provider = new OpenRouterProvider();

  expect(fn() => $provider->generate('system', 'user'))
    ->toThrow(\Exception::class, 'OpenRouter error: 500');
});

test('generate throws immediately on 401 unauthorized', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fake(['*' => Http::response('Unauthorized', 401)]);

  $provider = new OpenRouterProvider();

  expect(fn() => $provider->generate('system', 'user'))
    ->toThrow(\Exception::class, 'OpenRouter error: 401');
});

test('generate does not try next model on non-404 errors', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fake(['*' => Http::response('Server Error', 500)]);

  $provider = new OpenRouterProvider();

  expect(fn() => $provider->generate('system', 'user'))
    ->toThrow(\Exception::class);

  // طلب واحد فقط — لم يجرب الموديل الثاني
  Http::assertSentCount(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// generate() — Empty Response
// ─────────────────────────────────────────────────────────────────────────────

test('generate throws exception when api returns empty text', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fake(['*' => Http::response([
    'choices' => [['message' => ['content' => '']]],
  ], 200)]);

  $provider = new OpenRouterProvider();

  expect(fn() => $provider->generate('system', 'user'))
    ->toThrow(\Exception::class, 'empty response');
});

test('generate throws exception when choices are missing from response', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fake(['*' => Http::response(['data' => 'nothing useful'], 200)]);

  $provider = new OpenRouterProvider();

  expect(fn() => $provider->generate('system', 'user'))
    ->toThrow(\Exception::class, 'empty response');
});

// ─────────────────────────────────────────────────────────────────────────────
// generate() — Exception Messages
// ─────────────────────────────────────────────────────────────────────────────

test('final exception message mentions all models failed', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fake(['*' => Http::response(['error' => 'not found'], 404)]);

  $provider = new OpenRouterProvider();

  expect(fn() => $provider->generate('system', 'user'))
    ->toThrow(\Exception::class, 'OpenRouter: All models failed');
});

test('final exception message contains the last error', function () {
  Log::spy();
  setOpenRouterConfig();

  Http::fake(['*' => Http::response(['error' => 'not found'], 404)]);

  $provider = new OpenRouterProvider();

  expect(fn() => $provider->generate('system', 'user'))
    ->toThrow(\Exception::class, 'Last error:');
});
