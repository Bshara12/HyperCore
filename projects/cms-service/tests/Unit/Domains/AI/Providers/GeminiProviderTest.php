<?php

namespace Tests\Unit\Domains\AI\Providers;

use App\Domains\AI\Providers\GeminiProvider;

// ─── Helpers ──────────────────────────────────────────────────────────────────
afterEach(fn () => \Mockery::close());
require_once __DIR__ . '/Helpers.php';


afterEach(fn () => \Mockery::close());

// ─── getName() ────────────────────────────────────────────────────────────────

test('getName returns the default label when no label is provided', function () {
    $provider = new GeminiProvider('api-key', 'gemini-2.5-flash');

    expect($provider->getName())->toBe('Gemini');
});

test('getName returns the custom label when provided', function () {
    $provider = new GeminiProvider('api-key', 'gemini-2.5-flash', 'Gemini #2');

    expect($provider->getName())->toBe('Gemini #2');
});

// ─── isAvailable() ────────────────────────────────────────────────────────────

test('isAvailable returns true when api key is not empty', function () {
    $provider = new GeminiProvider('valid-api-key', 'gemini-2.5-flash');

    expect($provider->isAvailable())->toBeTrue();
});

test('isAvailable returns false when api key is empty string', function () {
    $provider = new GeminiProvider('', 'gemini-2.5-flash');

    expect($provider->isAvailable())->toBeFalse();
});

// ─── generate() — Happy Path ──────────────────────────────────────────────────

test('generate returns text from the injected client', function () {
    $clientMock = makeGeminiClientMock('Hello from Gemini!');

    $provider = new GeminiProvider('api-key', 'gemini-2.5-flash', 'Gemini #1', $clientMock);
    $result = $provider->generate('System prompt', 'User prompt');

    expect($result)->toBe('Hello from Gemini!');
});



test('generate passes system and user prompts correctly to generateContent', function () {
    $resultMock = mock(\stdClass::class)->makePartial();
    $resultMock->shouldReceive('text')->andReturn('ok');

    $modelMock = mock(\stdClass::class)->makePartial();
    $modelMock->shouldReceive('generateContent')
        ->once()
        ->with(['My system prompt', 'User Request: My user prompt'])
        ->andReturn($resultMock);

    $clientMock = mock(\stdClass::class)->makePartial();
    $clientMock->shouldReceive('generativeModel')->andReturn($modelMock);

    $provider = new GeminiProvider('api-key', 'gemini-2.5-flash', 'Gemini #1', $clientMock);
    $provider->generate('My system prompt', 'My user prompt');
});

// ─── generate() — Empty Response ─────────────────────────────────────────────

test('generate throws an exception when the response text is empty', function () {
    $clientMock = makeGeminiClientMock('');

    $provider = new GeminiProvider('api-key', 'gemini-2.5-flash', 'Gemini #1', $clientMock);

    expect(fn () => $provider->generate('System', 'User'))
        ->toThrow(\Exception::class, 'Gemini #1 returned empty response.');
});

test('generate exception message contains the provider label', function () {
    $clientMock = makeGeminiClientMock('');

    $provider = new GeminiProvider('api-key', 'gemini-2.5-flash', 'My Custom Label', $clientMock);

    expect(fn () => $provider->generate('System', 'User'))
        ->toThrow(\Exception::class, 'My Custom Label returned empty response.');
});

// ─── generate() — Client Exceptions ──────────────────────────────────────────

test('generate propagates exceptions thrown by the Gemini client', function () {
    $modelMock = mock(\stdClass::class)->makePartial();
    $modelMock->shouldReceive('generateContent')
        ->andThrow(new \Exception('Network timeout'));

    $clientMock = mock(\stdClass::class)->makePartial();
    $clientMock->shouldReceive('generativeModel')->andReturn($modelMock);

    $provider = new GeminiProvider('api-key', 'gemini-2.5-flash', 'Gemini #1', $clientMock);

    expect(fn () => $provider->generate('System', 'User'))
        ->toThrow(\Exception::class, 'Network timeout');
});

// ─── Constructor & Integration ────────────────────────────────────────────────

test('provider uses injected client instead of creating a new one', function () {
    // نتأكد إن الـ client المحقون يُستخدم (لو استُدعي generativeModel يعني استخدمه)
    $clientMock = makeGeminiClientMock('Injected client response');

    $provider = new GeminiProvider('api-key', 'gemini-2.5-flash', 'Gemini #1', $clientMock);
    $result = $provider->generate('s', 'u');

    expect($result)->toBe('Injected client response');
});